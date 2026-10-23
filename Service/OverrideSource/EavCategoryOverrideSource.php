<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service\OverrideSource;

use AvS\ScopeHint\Api\Data\OverrideResultInterface;
use AvS\ScopeHint\Api\Data\ScopeOverrideInterface;
use AvS\ScopeHint\Api\OverrideSourceInterface;
use AvS\ScopeHint\Model\Data\OverrideResult;
use AvS\ScopeHint\Model\Data\ScopeOverride;
use AvS\ScopeHint\Service\EavOptionLabelResolver;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Default OverrideSourceInterface implementation for real Magento EAV category attributes.
 *
 * Supports fields whose meta config has a non-empty `code` key and is not globally
 * scoped. Memoises category loads per-request. Mirrors the shape of
 * EavProductOverrideSource for consistency.
 */
class EavCategoryOverrideSource implements OverrideSourceInterface
{
    /**
     * Config path for the maximum stores limit.
     */
    const XML_PATH_MAX_STORES = 'dev/debug/scopehint_max_stores';

    /**
     * Default maximum number of stores before detection is skipped.
     */
    const DEFAULT_MAX_STORES = 50;

    /** @var CategoryRepositoryInterface */
    private $categoryRepository;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var EavConfig */
    private $eavConfig;

    /** @var Escaper */
    private $escaper;

    /** @var LoggerInterface */
    private $logger;

    /** @var EavOptionLabelResolver */
    private $optionLabelResolver;

    /**
     * Per-request cache of category objects keyed by "categoryId_storeId".
     *
     * @var array
     */
    private $categoryCache = [];

    /**
     * Cached flat list of stores (populated once per request).
     *
     * @var StoreInterface[]|null
     */
    private $stores = null;

    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        EavConfig $eavConfig,
        Escaper $escaper,
        LoggerInterface $logger,
        EavOptionLabelResolver $optionLabelResolver
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->eavConfig = $eavConfig;
        $this->escaper = $escaper;
        $this->logger = $logger;
        $this->optionLabelResolver = $optionLabelResolver;
    }

    /**
     * @param string $entityType
     * @param array  $fieldMeta
     * @return bool
     */
    public function supports(string $entityType, array $fieldMeta): bool
    {
        if ($entityType !== 'category') {
            return false;
        }

        if (empty($fieldMeta['code'])) {
            return false;
        }

        try {
            $attribute = $this->eavConfig->getAttribute('catalog_category', (string) $fieldMeta['code']);
            if (!$attribute || !$attribute->getAttributeId()) {
                return false;
            }

            $isGlobal = method_exists($attribute, 'getIsGlobal')
                ? (int) $attribute->getIsGlobal()
                : (int) $attribute->getData('is_global');

            return $isGlobal !== ScopedAttributeInterface::SCOPE_GLOBAL;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param string $entityType
     * @param int    $entityId
     * @param array  $fieldMeta
     * @param int    $storeId
     * @return OverrideResultInterface
     */
    public function getOverrides(
        string $entityType,
        int $entityId,
        array $fieldMeta,
        int $storeId = 0
    ): OverrideResultInterface {
        $stores = $this->getStores();

        $maxStores = (int) $this->scopeConfig->getValue(self::XML_PATH_MAX_STORES);
        if ($maxStores <= 0) {
            $maxStores = self::DEFAULT_MAX_STORES;
        }

        if (count($stores) > $maxStores) {
            $this->logger->warning(
                sprintf(
                    'AvS_ScopeHint: skipping category EAV override detection — store count (%d) exceeds '
                    . 'dev/debug/scopehint_max_stores (%d).',
                    count($stores),
                    $maxStores
                )
            );

            return new OverrideResult([]);
        }

        $attributeCode = (string) $fieldMeta['code'];
        $currentCategory = $this->loadCategory($entityId, $storeId);

        if ($currentCategory === null) {
            return new OverrideResult([]);
        }

        $currentValue = $currentCategory->getData($attributeCode);
        $overrides = [];

        foreach ($stores as $store) {
            $otherStoreId = (int) $store->getId();

            if ($otherStoreId === $storeId) {
                continue;
            }

            $storeCategory = $this->loadCategory($entityId, $otherStoreId);

            if ($storeCategory === null) {
                continue;
            }

            $storeValue = $storeCategory->getData($attributeCode);

            if (!$this->valuesDiffer($currentValue, $storeValue)) {
                continue;
            }

            $displayValue = $this->resolveDisplayValue($attributeCode, $storeValue);
            $websiteCode = $this->resolveWebsiteCode($store);

            $overrides[] = new ScopeOverride(
                ScopeOverrideInterface::SCOPE_TYPE_STORES,
                (string) $store->getCode(),
                (string) $store->getName(),
                $otherStoreId,
                $websiteCode,
                $storeValue,
                $displayValue
            );
        }

        return new OverrideResult($overrides);
    }

    /**
     * @param mixed $current
     * @param mixed $other
     */
    private function valuesDiffer($current, $other): bool
    {
        if (is_array($current) || is_array($other)) {
            return $current !== $other;
        }

        return (string) $current !== (string) $other;
    }

    /**
     * @param mixed $value
     */
    private function resolveDisplayValue(string $attributeCode, $value): string
    {
        if (is_array($value)) {
            return $this->escaper->escapeHtml((string) __('(complex value)'));
        }

        if (!is_scalar($value) && !$value instanceof \Stringable) {
            $encoded = json_encode($value);
            $valueAsString = $encoded !== false ? $encoded : '';
        } else {
            $valueAsString = (string) $value;
        }

        if ($valueAsString !== '') {
            try {
                $attribute = $this->eavConfig->getAttribute('catalog_category', $attributeCode);
                if ($attribute) {
                    $valueAsString = $this->optionLabelResolver->resolve($attribute, $valueAsString);
                }
            } catch (\Throwable $ex) {
                // Attribute lookup failed: show the raw value.
            }
        }

        return $this->escaper->escapeHtml($valueAsString);
    }

    /**
     * @return \Magento\Catalog\Model\Category|null
     */
    private function loadCategory(int $categoryId, int $storeId)
    {
        $cacheKey = $categoryId . '_' . $storeId;

        if (!array_key_exists($cacheKey, $this->categoryCache)) {
            try {
                $this->categoryCache[$cacheKey] = $this->categoryRepository->get($categoryId, $storeId);
            } catch (NoSuchEntityException $e) {
                $this->categoryCache[$cacheKey] = null;
            }
        }

        return $this->categoryCache[$cacheKey];
    }

    /**
     * @return StoreInterface[]
     */
    private function getStores(): array
    {
        if ($this->stores === null) {
            $this->stores = array_values($this->storeManager->getStores(false));
        }

        return $this->stores;
    }

    private function resolveWebsiteCode(StoreInterface $store): string
    {
        try {
            return (string) $this->storeManager->getWebsite($store->getWebsiteId())->getCode();
        } catch (\Throwable $e) {
            return '';
        }
    }
}
