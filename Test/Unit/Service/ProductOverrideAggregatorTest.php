<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service;

use AvS\ScopeHint\Service\ProductOverrideAggregator;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as CatalogAttribute;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
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

        $this->aggregator = new ProductOverrideAggregator(
            $this->resource,
            $this->eavConfig,
            $this->storeManager
        );
    }

    public function testEmptyProductIdsReturnsEmptyArray(): void
    {
        $this->resource->expects($this->never())->method('getConnection');

        $result = $this->aggregator->getOverrides([]);

        $this->assertSame([], $result);
    }

    public function testReturnsOverrideForSingleVarcharAttribute(): void
    {
        $store   = $this->buildStore(2, 'store_en', 'English Store', 'website_base');
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getCode')->willReturn('website_base');

        $this->storeManager->method('getStores')->willReturn([$store]);
        $this->storeManager->method('getWebsite')->willReturn($website);

        $attribute = $this->buildAttribute('name', ScopedAttributeInterface::SCOPE_STORE, 'text');

        $this->eavConfig->method('getAttribute')
            ->with('catalog_product', 42)
            ->willReturn($attribute);

        $this->setupConnection([
            'catalog_product_entity_varchar' => [
                ['entity_id' => '1', 'attribute_id' => '42', 'store_id' => '2', 'value' => 'Overridden Name'],
            ],
            'catalog_product_entity_int'      => [],
            'catalog_product_entity_decimal'  => [],
            'catalog_product_entity_text'     => [],
            'catalog_product_entity_datetime' => [],
        ]);

        $result = $this->aggregator->getOverrides([1]);

        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey('name', $result[1]);
        $this->assertArrayHasKey(2, $result[1]['name']);
        $this->assertSame('Overridden Name', $result[1]['name'][2]['displayValue']);
        $this->assertSame('English Store', $result[1]['name'][2]['storeName']);
        $this->assertSame('store_en', $result[1]['name'][2]['storeCode']);
        $this->assertSame('website_base', $result[1]['name'][2]['websiteCode']);
    }

    public function testImageAttributeIsFiltered(): void
    {
        $store   = $this->buildStore(2, 'store_en', 'English Store', 'website_base');
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getCode')->willReturn('website_base');

        $this->storeManager->method('getStores')->willReturn([$store]);
        $this->storeManager->method('getWebsite')->willReturn($website);

        // attribute with frontend_input = image
        $attribute = $this->buildAttribute('image', ScopedAttributeInterface::SCOPE_STORE, 'image');

        $this->eavConfig->method('getAttribute')->willReturn($attribute);

        $this->setupConnection([
            'catalog_product_entity_varchar' => [
                ['entity_id' => '1', 'attribute_id' => '10', 'store_id' => '2', 'value' => '/i/m/img.jpg'],
            ],
            'catalog_product_entity_int'      => [],
            'catalog_product_entity_decimal'  => [],
            'catalog_product_entity_text'     => [],
            'catalog_product_entity_datetime' => [],
        ]);

        $result = $this->aggregator->getOverrides([1]);

        // image attribute must be silently skipped → no overrides
        $this->assertEmpty($result);
    }

    public function testGlobalScopeAttributeIsFiltered(): void
    {
        $store   = $this->buildStore(2, 'store_en', 'English Store', 'website_base');
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getCode')->willReturn('website_base');

        $this->storeManager->method('getStores')->willReturn([$store]);
        $this->storeManager->method('getWebsite')->willReturn($website);

        // global-scope attribute
        $attribute = $this->buildAttribute('sku', ScopedAttributeInterface::SCOPE_GLOBAL, 'text');

        $this->eavConfig->method('getAttribute')->willReturn($attribute);

        $this->setupConnection([
            'catalog_product_entity_varchar' => [
                ['entity_id' => '1', 'attribute_id' => '5', 'store_id' => '2', 'value' => 'SKU-002'],
            ],
            'catalog_product_entity_int'      => [],
            'catalog_product_entity_decimal'  => [],
            'catalog_product_entity_text'     => [],
            'catalog_product_entity_datetime' => [],
        ]);

        $result = $this->aggregator->getOverrides([1]);

        // global attribute must be silently skipped → no overrides
        $this->assertEmpty($result);
    }

    public function testCacheHitOnSecondCallWithSameIds(): void
    {
        $store   = $this->buildStore(2, 'store_en', 'English Store', 'website_base');
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getCode')->willReturn('website_base');

        $this->storeManager->method('getStores')->willReturn([$store]);
        $this->storeManager->method('getWebsite')->willReturn($website);

        $attribute = $this->buildAttribute('name', ScopedAttributeInterface::SCOPE_STORE, 'text');
        $this->eavConfig->method('getAttribute')->willReturn($attribute);

        $connection = $this->createMock(AdapterInterface::class);
        $this->resource->method('getConnection')->willReturn($connection);
        $this->resource->method('getTableName')->willReturnCallback(
            static function (string $table) {
                return $table;
            }
        );

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('joinInner')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection->method('select')->willReturn($select);

        // fetchAll should only be called 5 times (one per backend type) on the first call.
        $connection->expects($this->exactly(5))
            ->method('fetchAll')
            ->willReturn([]);

        $this->aggregator->getOverrides([1]);
        // Second call with same ids must hit cache and not invoke fetchAll again.
        $result = $this->aggregator->getOverrides([1]);

        $this->assertSame([], $result);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @param array<string, array<int, array<string, string>>> $tableRows table name => rows
     */
    private function setupConnection(array $tableRows): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $this->resource->method('getConnection')->willReturn($connection);
        $this->resource->method('getTableName')->willReturnCallback(
            static function (string $table) {
                return $table;
            }
        );

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('joinInner')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection->method('select')->willReturn($select);

        $fetchSequence = array_values($tableRows);
        $connection->method('fetchAll')->willReturnOnConsecutiveCalls(...$fetchSequence);
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

    private function buildAttribute(string $code, int $scope, string $frontendInput): CatalogAttribute
    {
        $attribute = $this->createMock(CatalogAttribute::class);
        $attribute->method('getAttributeId')->willReturn('42');
        $attribute->method('getAttributeCode')->willReturn($code);
        $attribute->method('getIsGlobal')->willReturn((string) $scope);
        $attribute->method('getFrontendInput')->willReturn($frontendInput);

        return $attribute;
    }
}
