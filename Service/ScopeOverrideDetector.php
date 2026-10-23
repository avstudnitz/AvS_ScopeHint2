<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service;

use AvS\ScopeHint\Api\Data\OverrideResultInterface;
use AvS\ScopeHint\Api\Data\ScopeOverrideInterface;
use AvS\ScopeHint\Model\Data\OverrideResult;
use AvS\ScopeHint\Model\Data\ScopeOverride;
use AvS\ScopeHint\Service\ValueFormatter\ParentAwareValueFormatterInterface;
use AvS\ScopeHint\Service\ValueFormatter\Pool;
use Magento\Config\Model\Config\Structure\Element\Field as Subject;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\ScopeInterface;

class ScopeOverrideDetector implements ScopeOverrideDetectorInterface
{
    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var WebsiteRepositoryInterface */
    private $websiteRepository;

    /** @var StoreRepositoryInterface */
    private $storeRepository;

    /** @var RequestInterface */
    private $request;

    /** @var Escaper */
    private $escaper;

    /** @var Pool */
    private $formatterPool;

    /** @var CollectionFactory */
    private $configCollectionFactory;

    /** @var array<string, array<string, array<int, mixed>>>|null */
    private $explicitValuesByPath;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WebsiteRepositoryInterface $websiteRepository,
        StoreRepositoryInterface $storeRepository,
        RequestInterface $request,
        Escaper $escaper,
        Pool $formatterPool,
        CollectionFactory $configCollectionFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->websiteRepository = $websiteRepository;
        $this->storeRepository = $storeRepository;
        $this->request = $request;
        $this->escaper = $escaper;
        $this->formatterPool = $formatterPool;
        $this->configCollectionFactory = $configCollectionFactory;
    }

    public function getOverrides(string $path, ?Subject $field = null): OverrideResultInterface
    {
        $currentWebsiteCode = $this->getWebsiteParam();
        $currentStoreCode = $this->getStoreParam();

        // A store view has no lower configuration scope.
        if ($currentStoreCode !== null) {
            return new OverrideResult([]);
        }

        $currentWebsiteId = null;
        if ($currentWebsiteCode !== null) {
            try {
                $currentWebsiteId = (int) $this->websiteRepository->get($currentWebsiteCode)->getId();
            } catch (\Throwable $e) {
                return new OverrideResult([]);
            }
        }

        $currentValue = $currentWebsiteId === null
            ? $this->scopeConfig->getValue($path)
            : $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_WEBSITE, $currentWebsiteId);
        $explicitValues = $this->getExplicitValues($path);

        $overrides = [];

        if ($currentWebsiteId === null) {
            foreach ($this->websiteRepository->getList() as $website) {
                $websiteId = (int) $website->getId();
                if (!array_key_exists($websiteId, $explicitValues[ScopeOverrideInterface::SCOPE_TYPE_WEBSITES])) {
                    continue;
                }
                $scopeValue = $explicitValues[ScopeOverrideInterface::SCOPE_TYPE_WEBSITES][$websiteId];
                if ($this->valuesDiffer($currentValue, $scopeValue)) {
                    $overrides[] = new ScopeOverride(
                        ScopeOverrideInterface::SCOPE_TYPE_WEBSITES,
                        (string) $website->getCode(),
                        (string) $website->getName(),
                        (int) $website->getId(),
                        (string) $website->getCode(),
                        $scopeValue,
                        $this->formatDisplayValue($scopeValue, $currentValue, $field)
                    );
                }
            }
        }

        foreach ($this->storeRepository->getList() as $store) {
            $storeId = (int) $store->getId();
            $websiteId = (int) $store->getWebsiteId();

            if ($currentWebsiteId !== null && $websiteId !== $currentWebsiteId) {
                continue;
            }
            if (!array_key_exists($storeId, $explicitValues[ScopeOverrideInterface::SCOPE_TYPE_STORES])) {
                continue;
            }

            $scopeValue = $explicitValues[ScopeOverrideInterface::SCOPE_TYPE_STORES][$storeId];
            $parentValue = $currentWebsiteId !== null
                ? $currentValue
                : $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_WEBSITE, $websiteId);

            if ($this->valuesDiffer($parentValue, $scopeValue)) {
                $overrides[] = new ScopeOverride(
                    ScopeOverrideInterface::SCOPE_TYPE_STORES,
                    (string) $store->getCode(),
                    (string) $store->getName(),
                    $storeId,
                    $this->resolveWebsiteCodeForStore($store),
                    $scopeValue,
                    $this->formatDisplayValue($scopeValue, $parentValue, $field)
                );
            }
        }

        return new OverrideResult($overrides);
    }

    /**
     * Read only values explicitly saved at a lower scope. ScopeConfig returns
     * inherited values, which would incorrectly report every child store when
     * only its website has an override.
     *
     * All website/store rows are loaded in one query per request: the plugin runs
     * once per rendered field, and a per-path query cannot use the
     * (scope, scope_id, path) unique index.
     *
     * @return array<string, array<int, mixed>>
     */
    private function getExplicitValues(string $path): array
    {
        if ($this->explicitValuesByPath === null) {
            $this->explicitValuesByPath = [];
            $collection = $this->configCollectionFactory->create();
            $collection->addFieldToFilter(
                'scope',
                ['in' => [ScopeOverrideInterface::SCOPE_TYPE_WEBSITES, ScopeOverrideInterface::SCOPE_TYPE_STORES]]
            );

            foreach ($collection as $configValue) {
                $scope = (string) $configValue->getScope();
                $this->explicitValuesByPath[(string) $configValue->getPath()][$scope][(int) $configValue->getScopeId()]
                    = $configValue->getValue();
            }
        }

        return ($this->explicitValuesByPath[$path] ?? []) + [
            ScopeOverrideInterface::SCOPE_TYPE_WEBSITES => [],
            ScopeOverrideInterface::SCOPE_TYPE_STORES => [],
        ];
    }

    /**
     * @param mixed $current
     * @param mixed $scoped
     */
    private function valuesDiffer($current, $scoped): bool
    {
        if (is_array($current) || is_array($scoped)) {
            // Detector exposes raw arrays for higher layers (formatter pool, popover);
            // any non-strict difference counts as an override.
            return $current !== $scoped;
        }
        return (string) $current !== (string) $scoped;
    }

    /**
     * @param mixed $value
     * @param mixed $parentValue
     */
    private function formatDisplayValue($value, $parentValue, ?Subject $field): string
    {
        if ($field !== null) {
            $formatter = $this->formatterPool->resolve($field);

            return $formatter instanceof ParentAwareValueFormatterInterface
                ? $formatter->formatAgainstParent($value, $parentValue, $field)
                : $formatter->format($value, $field);
        }

        // Fallback path: no field context available.
        if (is_array($value)) {
            return $this->escaper->escapeHtml((string) __('(complex value)'));
        }

        return $this->escaper->escapeHtml((string) $value);
    }

    private function resolveWebsiteCodeForStore(StoreInterface $store): string
    {
        try {
            return (string) $this->websiteRepository->getById((int) $store->getWebsiteId())->getCode();
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function getWebsiteParam(): ?string
    {
        $value = $this->request->getParam('website');
        return $value === null || $value === '' ? null : (string) $value;
    }

    private function getStoreParam(): ?string
    {
        $value = $this->request->getParam('store');
        return $value === null || $value === '' ? null : (string) $value;
    }
}
