<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service;

use AvS\ScopeHint\Service\EavOptionLabelResolver;
use AvS\ScopeHint\Service\ProductOverrideAggregator;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as CatalogAttribute;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductOverrideAggregatorTest extends TestCase
{
    /** @var ResourceConnection&MockObject */
    private $resource;

    /** @var EavConfig&MockObject */
    private $eavConfig;

    /** @var StoreManagerInterface&MockObject */
    private $storeManager;

    /** @var ProductOverrideAggregator */
    private $aggregator;

    protected function setUp(): void
    {
        $this->resource     = $this->createMock(ResourceConnection::class);
        $this->eavConfig    = $this->createMock(EavConfig::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn('entity_id');
        $metadata->method('getIdentifierField')->willReturn('entity_id');
        $metadataPool = $this->createMock(MetadataPool::class);
        $metadataPool->method('getMetadata')->willReturn($metadata);

        $this->aggregator = new ProductOverrideAggregator(
            $this->resource,
            $this->eavConfig,
            $this->storeManager,
            $metadataPool,
            new EavOptionLabelResolver()
        );
    }

    public function testEmptyProductIdsReturnsEmptyArray(): void
    {
        $this->resource->expects($this->never())->method('getConnection');

        $this->assertSame([], $this->aggregator->getOverrides([], ['name']));
    }

    public function testReturnsOverrideForSingleVarcharAttribute(): void
    {
        $this->stubStores();
        $this->stubAttributes([$this->buildAttribute('name', 42, 'varchar', 'text')]);
        $select = $this->setupConnection([
            ['entity_id' => '1', 'attribute_id' => '42', 'store_id' => '2', 'value' => 'Overridden Name'],
        ]);

        $select->expects($this->once())->method('from')
            ->with(['t' => 'catalog_product_entity_varchar'], $this->anything());

        $result = $this->aggregator->getOverrides([1], ['entity_id', 'name']);

        $this->assertSame('Overridden Name', $result[1]['name'][2]['displayValue']);
        $this->assertSame('English Store', $result[1]['name'][2]['storeName']);
        $this->assertSame('store_en', $result[1]['name'][2]['storeCode']);
        $this->assertSame('website_base', $result[1]['name'][2]['websiteCode']);
    }

    public function testQueriesOnlyRequestedAttributesWithLeftJoinOnAdminRow(): void
    {
        $this->stubStores();
        $this->stubAttributes([$this->buildAttribute('name', 42, 'varchar', 'text')]);
        $select = $this->setupConnection([]);

        $select->expects($this->once())->method('joinLeft')
            ->with(['admin' => 'catalog_product_entity_varchar'], $this->anything(), []);
        $whereClauses = [];
        $select->method('where')->willReturnCallback(
            function (string $condition, $value = null) use (&$whereClauses, $select) {
                $whereClauses[$condition] = $value;

                return $select;
            }
        );

        $this->aggregator->getOverrides([1], ['name']);

        $this->assertSame([42], $whereClauses['t.attribute_id IN (?)']);
    }

    public function testSkipsQueryWhenNoRequestedCodeIsAScopedAttribute(): void
    {
        $this->stubAttributes([
            $this->buildAttribute('image', 10, 'varchar', 'image'),
            $this->buildAttribute('sku', 5, 'static', 'text', ScopedAttributeInterface::SCOPE_GLOBAL),
            $this->buildAttribute('weight', 6, 'decimal', 'weight', ScopedAttributeInterface::SCOPE_GLOBAL),
        ]);
        $this->resource->expects($this->never())->method('getConnection');

        $this->assertSame([], $this->aggregator->getOverrides([1], ['entity_id', 'image', 'sku', 'weight', 'qty']));
    }

    public function testResolvesSelectOptionLabel(): void
    {
        $this->stubStores();
        $status = $this->buildAttribute('status', 97, 'int', 'select');
        $status->method('usesSource')->willReturn(true);
        $status->method('getSource')->willReturn($this->buildSource(['2' => 'Disabled']));
        $this->stubAttributes([$status]);
        $this->setupConnection([
            ['entity_id' => '1', 'attribute_id' => '97', 'store_id' => '2', 'value' => '2'],
        ]);

        $result = $this->aggregator->getOverrides([1], ['status']);

        $this->assertSame('Disabled', $result[1]['status'][2]['displayValue']);
    }

    public function testResolvesMultiselectOptionLabels(): void
    {
        $this->stubStores();
        $material = $this->buildAttribute('material', 150, 'varchar', 'multiselect');
        $material->method('usesSource')->willReturn(true);
        $material->method('getSource')->willReturn($this->buildSource(['4,5' => ['Cotton', 'Wool']]));
        $this->stubAttributes([$material]);
        $this->setupConnection([
            ['entity_id' => '1', 'attribute_id' => '150', 'store_id' => '2', 'value' => '4,5'],
        ]);

        $result = $this->aggregator->getOverrides([1], ['material']);

        $this->assertSame('Cotton, Wool', $result[1]['material'][2]['displayValue']);
    }

    public function testCacheHitOnSecondCallWithSameIdsAndCodes(): void
    {
        $this->stubStores();
        $this->stubAttributes([$this->buildAttribute('name', 42, 'varchar', 'text')]);
        $this->setupConnection([]);
        $this->resource->expects($this->once())->method('getConnection');

        $this->aggregator->getOverrides([1], ['name']);

        $this->assertSame([], $this->aggregator->getOverrides([1], ['name']));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @param array<int, array<string, string>> $rows
     * @return Select&MockObject
     */
    private function setupConnection(array $rows): Select
    {
        $connection = $this->createMock(AdapterInterface::class);
        $this->resource->method('getConnection')->willReturn($connection);
        $this->resource->method('getTableName')->willReturnArgument(0);

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('joinInner')->willReturnSelf();
        $select->method('joinLeft')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn($rows);

        return $select;
    }

    private function stubStores(): void
    {
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getCode')->willReturn('website_base');
        $this->storeManager->method('getStores')->willReturn([
            $this->buildStore(2, 'store_en', 'English Store', 'website_base'),
        ]);
        $this->storeManager->method('getWebsite')->willReturn($website);
    }

    /**
     * @param CatalogAttribute[] $attributes
     */
    private function stubAttributes(array $attributes): void
    {
        $attributesByCode = [];
        foreach ($attributes as $attribute) {
            $attributesByCode[$attribute->getAttributeCode()] = $attribute;
        }
        $unknownAttribute = $this->createMock(CatalogAttribute::class);
        $unknownAttribute->method('getAttributeId')->willReturn(null);

        $this->eavConfig->method('getAttribute')->willReturnCallback(
            static function (string $entityType, string $code) use ($attributesByCode, $unknownAttribute) {
                return $attributesByCode[$code] ?? $unknownAttribute;
            }
        );
    }

    /**
     * @param array<string, string|string[]> $labelsByValue
     */
    private function buildSource(array $labelsByValue): AbstractSource
    {
        $source = $this->createMock(AbstractSource::class);
        $source->method('getOptionText')->willReturnCallback(
            static function ($value) use ($labelsByValue) {
                return $labelsByValue[$value] ?? false;
            }
        );

        return $source;
    }

    private function buildStore(int $id, string $code, string $name, string $websiteCode): StoreInterface
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn((string) $id);
        $store->method('getCode')->willReturn($code);
        $store->method('getName')->willReturn($name);
        $store->method('getWebsiteId')->willReturn('1');

        return $store;
    }

    private function buildAttribute(
        string $code,
        int $attributeId,
        string $backendType,
        string $frontendInput,
        int $scope = ScopedAttributeInterface::SCOPE_STORE
    ): CatalogAttribute {
        $attribute = $this->createMock(CatalogAttribute::class);
        $attribute->method('getAttributeId')->willReturn((string) $attributeId);
        $attribute->method('getAttributeCode')->willReturn($code);
        $attribute->method('getBackendType')->willReturn($backendType);
        $attribute->method('getIsGlobal')->willReturn((string) $scope);
        $attribute->method('getFrontendInput')->willReturn($frontendInput);

        return $attribute;
    }
}
