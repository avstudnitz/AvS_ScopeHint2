<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service\OverrideSource;

use AvS\ScopeHint\Api\Data\OverrideResultInterface;
use AvS\ScopeHint\Api\Data\ScopeOverrideInterface;
use AvS\ScopeHint\Api\OverrideSourceInterface;
use AvS\ScopeHint\Model\Data\OverrideResult;
use AvS\ScopeHint\Model\Data\ScopeOverride;
use AvS\ScopeHint\Service\EavOptionLabelResolver;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Default OverrideSourceInterface implementation for real Magento EAV product attributes.
 *
 * Supports fields whose meta config has a non-empty `code` key, is not globally
 * scoped (globalScope=false/null), and is not the composite quantity_and_stock_status
 * attribute. Preserves the exact behaviour of the previous
 * ProductEavDataProviderPlugin implementation.
 */
class EavProductOverrideSource implements OverrideSourceInterface
{
    /**
     * Config path for the maximum stores limit.
     */
    const XML_PATH_MAX_STORES = 'dev/debug/scopehint_max_stores';

    /**
     * Default maximum number of stores before detection is skipped.
     */
    const DEFAULT_MAX_STORES = 50;

    /** @var ProductRepositoryInterface */
    private $productRepository;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var Escaper */
    private $escaper;

    /** @var LoggerInterface */
    private $logger;

    /** @var EavOptionLabelResolver */
    private $optionLabelResolver;

    /**
     * Per-request cache of product objects keyed by "productId_storeId".
     *
     * @var array
     */
    private $productCache = [];

    /**
     * Cached flat list of stores (populated once per request).
     *
     * @var StoreInterface[]|null
     */
    private $stores = null;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        Escaper $escaper,
        LoggerInterface $logger,
        EavOptionLabelResolver $optionLabelResolver
    ) {
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
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
        if ($entityType !== 'product') {
            return false;
        }

        if (empty($fieldMeta['code'])) {
            return false;
        }

        if (!empty($fieldMeta['globalScope'])) {
            return false;
        }

        if ($fieldMeta['code'] === 'quantity_and_stock_status') {
            return false;
        }

        return true;
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
                    'AvS_ScopeHint: skipping product EAV override detection — store count (%d) exceeds '
                    . 'dev/debug/scopehint_max_stores (%d).',
                    count($stores),
                    $maxStores
                )
            );

            return new OverrideResult([]);
        }

        $attributeCode = (string) $fieldMeta['code'];
        $currentProduct = $this->loadProduct($entityId, $storeId);

        if ($currentProduct === null) {
            return new OverrideResult([]);
        }

        $currentValue = $currentProduct->getData($attributeCode);
        $overrides = [];

        foreach ($stores as $store) {
            $otherStoreId = (int) $store->getId();

            if ($otherStoreId === $storeId) {
                continue;
            }

            $productInStore = $this->loadProduct($entityId, $otherStoreId);

            if ($productInStore === null) {
                continue;
            }

            $storeValue = $productInStore->getData($attributeCode);

            if (!$this->valuesDiffer($currentValue, $storeValue)) {
                continue;
            }

            $displayValue = $this->resolveDisplayValue($productInStore, $attributeCode, $storeValue);
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
     * @param \Magento\Catalog\Model\Product $product
     * @param mixed $value
     */
    private function resolveDisplayValue($product, string $attributeCode, $value): string
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
                /** @var \Magento\Catalog\Model\ResourceModel\Product $productResource */
                $productResource = $product->getResource();
                $attribute = $productResource->getAttribute($attributeCode);
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
     * @return \Magento\Catalog\Model\Product|null
     */
    private function loadProduct(int $productId, int $storeId)
    {
        $cacheKey = $productId . '_' . $storeId;

        if (!array_key_exists($cacheKey, $this->productCache)) {
            try {
                $this->productCache[$cacheKey] = $this->productRepository->getById($productId, false, $storeId);
            } catch (NoSuchEntityException $e) {
                $this->productCache[$cacheKey] = null;
            }
        }

        return $this->productCache[$cacheKey];
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
