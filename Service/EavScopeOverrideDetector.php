<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service;

use AvS\ScopeHint\Api\Data\OverrideResultInterface;
use AvS\ScopeHint\Model\Data\OverrideResult;
use AvS\ScopeHint\Model\Data\ScopeOverride;
use AvS\ScopeHint\Api\Data\ScopeOverrideInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as EavAttribute;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Detects store-view-level EAV overrides for a single category attribute.
 */
class EavScopeOverrideDetector
{
    /**
     * Config path for the maximum stores limit.
     */
    const XML_PATH_MAX_STORES = 'dev/debug/scopehint_max_stores';

    /**
     * Default maximum number of stores before detection is skipped.
     */
    const DEFAULT_MAX_STORES = 50;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var CategoryRepositoryInterface */
    private $categoryRepository;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var Escaper */
    private $escaper;

    /** @var LoggerInterface */
    private $logger;

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
        StoreManagerInterface $storeManager,
        CategoryRepositoryInterface $categoryRepository,
        ScopeConfigInterface $scopeConfig,
        Escaper $escaper,
        LoggerInterface $logger
    ) {
        $this->storeManager = $storeManager;
        $this->categoryRepository = $categoryRepository;
        $this->scopeConfig = $scopeConfig;
        $this->escaper = $escaper;
        $this->logger = $logger;
    }

    /**
     * Returns all stores whose value for the given attribute differs from the
     * value at the current scope. The current category is loaded for the given
     * $currentStoreId (or store 0 / default when null).
     *
     * @param int $categoryId
     * @param EavAttribute $attribute
     * @param int|null $currentStoreId
     * @return OverrideResultInterface
     */
    public function getCategoryOverrides(
        int $categoryId,
        EavAttribute $attribute,
        ?int $currentStoreId
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

        $attributeCode = $attribute->getAttributeCode();
        $currentCategory = $this->loadCategory($categoryId, $currentStoreId ?? 0);

        if ($currentCategory === null) {
            return new OverrideResult([]);
        }

        $currentValue = $currentCategory->getData($attributeCode);
        $overrides = [];

        foreach ($stores as $store) {
            $storeId = (int) $store->getId();

            if ($currentStoreId !== null && $storeId === $currentStoreId) {
                continue;
            }

            $storeCategory = $this->loadCategory($categoryId, $storeId);

            if ($storeCategory === null) {
                continue;
            }

            $storeValue = $storeCategory->getData($attributeCode);

            if (!$this->valuesDiffer($currentValue, $storeValue)) {
                continue;
            }

            $displayValue = $this->resolveDisplayValue($attribute, $storeValue);
            $websiteCode = $this->resolveWebsiteCode($store);

            $overrides[] = new ScopeOverride(
                ScopeOverrideInterface::SCOPE_TYPE_STORES,
                (string) $store->getCode(),
                (string) $store->getName(),
                $storeId,
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
    private function resolveDisplayValue(EavAttribute $attribute, $value): string
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

        if ($attribute->getFrontendInput() === 'select' && $valueAsString !== '') {
            try {
                $optionText = $attribute->getSource()->getOptionText($valueAsString);
                if ($optionText !== false && $optionText !== null) {
                    $valueAsString = (string) $optionText;
                }
            } catch (\Throwable $ex) {
                // fall through — return raw value
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
