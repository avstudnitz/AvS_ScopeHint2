<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Ui\Component\Listing\Columns;

use AvS\ScopeHint\Ui\Component\Listing\Column\StoreOverrideIndicator;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\StoreManagerInterface;

class ProductOverrideColumns extends \Magento\Catalog\Ui\Component\Listing\Columns
{
    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var ObjectManagerInterface */
    private $objectManager;

    public function __construct(
        ContextInterface $context,
        \Magento\Catalog\Ui\Component\ColumnFactory $columnFactory,
        \Magento\Catalog\Ui\Component\Listing\Attribute\RepositoryInterface $attributeRepository,
        StoreManagerInterface $storeManager,
        ObjectManagerInterface $objectManager,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $columnFactory, $attributeRepository, $components, $data);
        $this->storeManager = $storeManager;
        $this->objectManager = $objectManager;
    }

    /**
     * @inheritdoc
     */
    public function prepare()
    {
        foreach ($this->storeManager->getStores(false) as $store) {
            $storeId = (int) $store->getId();
            $columnName = 'scopehint_overrides_' . $storeId;

            if (isset($this->components[$columnName])) {
                continue;
            }

            $config = [
                'label'     => __('Overrides: %1', $store->getName()),
                'dataType'  => 'text',
                'component' => 'Magento_Ui/js/grid/columns/column',
                'bodyTmpl'  => 'ui/grid/cells/html',
                'visible'   => false,
                'sortable'  => true,
                'filter'    => false,
                'sortOrder' => 200 + $storeId,
                'storeId'   => $storeId,
            ];

            /** @var StoreOverrideIndicator $column */
            $column = $this->objectManager->create(
                StoreOverrideIndicator::class,
                [
                    'name'    => $columnName,
                    'context' => $this->getContext(),
                    'data'    => ['config' => $config],
                ]
            );

            $column->prepare();
            $this->addComponent($columnName, $column);
        }

        parent::prepare();
    }
}
