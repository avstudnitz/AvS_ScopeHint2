<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Service;

use AvS\ScopeHint\Api\Data\OverrideResultInterface;
use AvS\ScopeHint\Api\Data\ScopeOverrideInterface;
use AvS\ScopeHint\Model\Data\OverrideResult;
use AvS\ScopeHint\Model\Data\ScopeOverride;
use Magento\Config\Model\Config\Structure\Element\Field as Subject;
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

    /** @var OptionLabelResolver */
    private $labelResolver;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WebsiteRepositoryInterface $websiteRepository,
        StoreRepositoryInterface $storeRepository,
        RequestInterface $request,
        Escaper $escaper,
        OptionLabelResolver $labelResolver
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->websiteRepository = $websiteRepository;
        $this->storeRepository = $storeRepository;
        $this->request = $request;
        $this->escaper = $escaper;
        $this->labelResolver = $labelResolver;
    }

    public function getOverrides(string $path, ?Subject $field = null): OverrideResultInterface
    {
        $currentWebsiteId = $this->getWebsiteParam();
        $currentStoreId = $this->getStoreParam();
        $currentValue = $this->getCurrentValue($path, $currentWebsiteId);

        $overrides = [];

        if ($currentWebsiteId === null && $currentStoreId === null) {
            foreach ($this->websiteRepository->getList() as $website) {
                $scopeValue = $this->scopeConfig->getValue(
                    $path,
                    ScopeOverrideInterface::SCOPE_TYPE_WEBSITES,
                    $website->getId()
                );
                if ($this->valuesDiffer($currentValue, $scopeValue)) {
                    $overrides[] = new ScopeOverride(
                        ScopeOverrideInterface::SCOPE_TYPE_WEBSITES,
                        (string) $website->getCode(),
                        (string) $website->getName(),
                        (int) $website->getId(),
                        (string) $website->getCode(),
                        $scopeValue,
                        $this->formatDisplayValue($scopeValue, $field)
                    );
                }
            }
        }

        foreach ($this->storeRepository->getList() as $store) {
            if ($currentStoreId !== null && (string) $store->getId() === $currentStoreId) {
                continue;
            }
            if ($currentWebsiteId !== null && (string) $store->getWebsiteId() !== $currentWebsiteId) {
                continue;
            }
            $scopeValue = $this->scopeConfig->getValue(
                $path,
                ScopeOverrideInterface::SCOPE_TYPE_STORES,
                $store->getId()
            );
            if ($this->valuesDiffer($currentValue, $scopeValue)) {
                $overrides[] = new ScopeOverride(
                    ScopeOverrideInterface::SCOPE_TYPE_STORES,
                    (string) $store->getCode(),
                    (string) $store->getName(),
                    (int) $store->getId(),
                    $this->resolveWebsiteCodeForStore($store),
                    $scopeValue,
                    $this->formatDisplayValue($scopeValue, $field)
                );
            }
        }

        return new OverrideResult($overrides);
    }

    /**
     * @return mixed
     */
    private function getCurrentValue(string $path, ?string $currentWebsiteId)
    {
        if ($currentWebsiteId !== null) {
            return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_WEBSITE, $currentWebsiteId);
        }
        return $this->scopeConfig->getValue($path);
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
     */
    private function formatDisplayValue($value, ?Subject $field): string
    {
        if (is_array($value)) {
            return $this->escaper->escapeHtml((string) __('(complex value)'));
        }
        $resolved = $field !== null
            ? $this->labelResolver->resolve($field, (string) $value)
            : (string) $value;
        return $this->escaper->escapeHtml($resolved);
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
