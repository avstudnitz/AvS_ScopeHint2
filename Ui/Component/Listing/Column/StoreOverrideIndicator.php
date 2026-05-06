<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Ui\Component\Listing\Column;

use AvS\ScopeHint\Service\ProductOverrideAggregator;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class StoreOverrideIndicator extends Column
{
    /** @var ProductOverrideAggregator */
    private $aggregator;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        ProductOverrideAggregator $aggregator,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->aggregator = $aggregator;
    }

    /**
     * @param array<string, mixed> $dataSource
     * @return array<string, mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $storeId = (int) $this->getData('config/storeId');

        if ($storeId === 0) {
            return $dataSource;
        }

        $productIds = [];

        foreach ($dataSource['data']['items'] as $item) {
            if (isset($item['entity_id'])) {
                $productIds[] = (int) $item['entity_id'];
            }
        }

        if (empty($productIds)) {
            return $dataSource;
        }

        $countsMap = $this->aggregator->getOverrideCounts($productIds);
        $columnName = $this->getName();

        foreach ($dataSource['data']['items'] as &$item) {
            $entityId = isset($item['entity_id']) ? (int) $item['entity_id'] : 0;
            $count = $countsMap[$entityId][$storeId] ?? 0;

            if ($count > 0) {
                $item[$columnName] = '<span class="scopehint-overrides-badge" title="'
                    . (string) __('%1 attribute(s) overridden', $count)
                    . '">'
                    . (int) $count
                    . '</span>';
            } else {
                $item[$columnName] = '';
            }
        }

        return $dataSource;
    }
}
