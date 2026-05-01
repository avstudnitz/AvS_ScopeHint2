<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Ui\Component\Listing\Column;

use AvS\ScopeHint\Service\ProductOverrideAggregator;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ScopeOverrideIndicator extends Column
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

        $productIds = [];

        foreach ($dataSource['data']['items'] as $item) {
            if (isset($item['entity_id'])) {
                $productIds[] = (int) $item['entity_id'];
            }
        }

        if (empty($productIds)) {
            return $dataSource;
        }

        $overrideCounts = $this->aggregator->getOverrideCounts($productIds);
        $columnName = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $entityId = isset($item['entity_id']) ? (int) $item['entity_id'] : 0;
            $count = $overrideCounts[$entityId] ?? 0;

            if ($count > 0) {
                $item[$columnName] = '<span class="scopehint-overrides-badge" title="'
                    . (string) __('%1 store(s) have overrides on key attributes', $count)
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
