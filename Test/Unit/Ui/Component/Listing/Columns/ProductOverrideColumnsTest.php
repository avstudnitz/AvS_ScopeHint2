<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Ui\Component\Listing\Columns;

use AvS\ScopeHint\Ui\Component\Listing\Column\StoreOverrideIndicator;
use AvS\ScopeHint\Ui\Component\Listing\Columns\ProductOverrideColumns;
use Magento\Catalog\Ui\Component\ColumnFactory;
use Magento\Catalog\Ui\Component\Listing\Attribute\RepositoryInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponent\Processor;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductOverrideColumnsTest extends TestCase
{
    /** @var ContextInterface&MockObject */
    private $context;

    /** @var ColumnFactory&MockObject */
    private $columnFactory;

    /** @var RepositoryInterface&MockObject */
    private $attributeRepository;

    /** @var StoreManagerInterface&MockObject */
    private $storeManager;

    /** @var ObjectManagerInterface&MockObject */
    private $objectManager;

    protected function setUp(): void
    {
        $this->context = $this->createMock(ContextInterface::class);
        $this->columnFactory = $this->createMock(ColumnFactory::class);
        $this->attributeRepository = $this->createMock(RepositoryInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);

        // Prevent the parent prepare() from iterating attributes
        $this->attributeRepository->method('getList')->willReturn([]);

        // Mock the context's processor to avoid null-dereference in AbstractComponent::prepare()
        $processor = $this->createMock(Processor::class);
        $this->context->method('getProcessor')->willReturn($processor);
        $this->context->method('addComponentDefinition')->willReturn(null);
    }

    private function makeStore(int $id, string $name): StoreInterface
    {
        /** @var StoreInterface&MockObject $store */
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn((string) $id);
        $store->method('getName')->willReturn($name);

        return $store;
    }

    private function buildColumns(array $components = []): ProductOverrideColumns
    {
        return new ProductOverrideColumns(
            $this->context,
            $this->columnFactory,
            $this->attributeRepository,
            $this->storeManager,
            $this->objectManager,
            $components,
            ['name' => 'product_columns']
        );
    }

    public function testPrepareAddsOneColumnPerStore(): void
    {
        $store1 = $this->makeStore(1, 'Default Store');
        $store2 = $this->makeStore(2, 'Second Store');

        $this->storeManager->method('getStores')->with(false)->willReturn([$store1, $store2]);

        $addedNames = [];
        $mockColumn = $this->createMock(StoreOverrideIndicator::class);
        $mockColumn->method('prepare')->willReturn(null);
        $mockColumn->method('getComponentName')->willReturn('column');

        $this->objectManager->method('create')
            ->willReturnCallback(function (string $class, array $args) use ($mockColumn, &$addedNames) {
                $addedNames[] = $args['name'];
                return $mockColumn;
            });

        $columns = $this->buildColumns();
        $columns->prepare();

        $this->assertContains('scopehint_overrides_1', $addedNames);
        $this->assertContains('scopehint_overrides_2', $addedNames);
        $this->assertCount(2, $addedNames);
    }

    public function testColumnArgsHaveCorrectDefaults(): void
    {
        $store1 = $this->makeStore(1, 'My Store');

        $this->storeManager->method('getStores')->with(false)->willReturn([$store1]);

        $capturedArgs = null;
        $mockColumn = $this->createMock(StoreOverrideIndicator::class);
        $mockColumn->method('prepare')->willReturn(null);
        $mockColumn->method('getComponentName')->willReturn('column');

        $this->objectManager->method('create')
            ->willReturnCallback(function (string $class, array $args) use ($mockColumn, &$capturedArgs) {
                $capturedArgs = $args;
                return $mockColumn;
            });

        $columns = $this->buildColumns();
        $columns->prepare();

        $this->assertNotNull($capturedArgs);
        $config = $capturedArgs['data']['config'];

        $this->assertFalse($config['visible'], 'Column should be hidden by default');
        $this->assertTrue($config['sortable'], 'Column should be sortable');
        $this->assertStringContainsString('My Store', (string) $config['label']);
        $this->assertSame(1, $config['storeId']);
    }

    public function testAlreadyExistingComponentIsNotDuplicated(): void
    {
        $store1 = $this->makeStore(1, 'Store One');

        $this->storeManager->method('getStores')->with(false)->willReturn([$store1]);

        // Pre-populate the component so it already exists
        $existingColumn = $this->createMock(StoreOverrideIndicator::class);
        $existingColumn->method('prepare')->willReturn(null);
        $existingColumn->method('getComponentName')->willReturn('column');

        $this->objectManager->expects($this->never())->method('create');

        $columns = $this->buildColumns(['scopehint_overrides_1' => $existingColumn]);
        $columns->prepare();

        // No exception and create() not called = correct
        $this->addToAssertionCount(1);
    }
}
