<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service\OverrideSource;

use AvS\ScopeHint\Api\Data\ScopeOverrideInterface;
use AvS\ScopeHint\Service\OverrideSource\EavProductOverrideSource;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class EavProductOverrideSourceTest extends TestCase
{
    /** @var ProductRepositoryInterface&MockObject */
    private $productRepository;

    /** @var StoreManagerInterface&MockObject */
    private $storeManager;

    /** @var ScopeConfigInterface&MockObject */
    private $scopeConfig;

    /** @var Escaper&MockObject */
    private $escaper;

    /** @var LoggerInterface&MockObject */
    private $logger;

    /** @var EavProductOverrideSource */
    private $source;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->storeManager      = $this->createMock(StoreManagerInterface::class);
        $this->scopeConfig       = $this->createMock(ScopeConfigInterface::class);
        $this->escaper           = $this->createMock(Escaper::class);
        $this->logger            = $this->createMock(LoggerInterface::class);

        $this->escaper->method('escapeHtml')->willReturnArgument(0);

        // Default: max_stores not exceeded
        $this->scopeConfig
            ->method('getValue')
            ->with(EavProductOverrideSource::XML_PATH_MAX_STORES)
            ->willReturn(50);

        $this->source = new EavProductOverrideSource(
            $this->productRepository,
            $this->storeManager,
            $this->scopeConfig,
            $this->escaper,
            $this->logger
        );
    }

    // ------------------------------------------------------------------
    // supports() tests
    // ------------------------------------------------------------------

    public function testSupportsReturnsFalseForCategoryEntityType(): void
    {
        self::assertFalse($this->source->supports('category', ['code' => 'name']));
    }

    public function testSupportsReturnsFalseWhenCodeIsMissing(): void
    {
        self::assertFalse($this->source->supports('product', []));
    }

    public function testSupportsReturnsFalseWhenCodeIsEmpty(): void
    {
        self::assertFalse($this->source->supports('product', ['code' => '']));
    }

    public function testSupportsReturnsFalseWhenGlobalScopeIsTrue(): void
    {
        self::assertFalse($this->source->supports('product', ['code' => 'name', 'globalScope' => true]));
    }

    public function testSupportsReturnsFalseForQuantityAndStockStatus(): void
    {
        self::assertFalse(
            $this->source->supports('product', ['code' => 'quantity_and_stock_status'])
        );
    }

    public function testSupportsReturnsTrueForValidProductField(): void
    {
        self::assertTrue($this->source->supports('product', ['code' => 'meta_description']));
    }

    public function testSupportsReturnsTrueWhenGlobalScopeIsFalsy(): void
    {
        self::assertTrue(
            $this->source->supports('product', ['code' => 'name', 'globalScope' => false])
        );
    }

    // ------------------------------------------------------------------
    // getOverrides() tests
    // ------------------------------------------------------------------

    public function testGetOverridesReturnsEmptyResultWhenNoStores(): void
    {
        $this->storeManager->method('getStores')->willReturn([]);

        $defaultProduct = $this->createProduct(10, 'name', 'My Product');
        $this->productRepository->method('getById')->willReturn($defaultProduct);

        $result = $this->source->getOverrides('product', 10, ['code' => 'name']);

        self::assertTrue($result->isEmpty());
    }

    public function testGetOverridesReturnsEmptyResultWhenProductNotFound(): void
    {
        $store = $this->createStore(1, 'default', 'Default Store', 1);
        $this->storeManager->method('getStores')->willReturn([$store]);

        $this->productRepository
            ->method('getById')
            ->willThrowException(new NoSuchEntityException());

        $result = $this->source->getOverrides('product', 999, ['code' => 'name']);

        self::assertTrue($result->isEmpty());
    }

    public function testGetOverridesDetectsStoreOverride(): void
    {
        $store = $this->createStore(2, 'de', 'German Store', 1);
        $this->storeManager->method('getStores')->willReturn([$store]);

        $website = $this->createWebsite(1, 'base');
        $this->storeManager->method('getWebsite')->with(1)->willReturn($website);

        $defaultProduct = $this->createProduct(5, 'name', 'Original Name');
        $storeProduct   = $this->createProduct(5, 'name', 'Überschriebener Name');

        $this->productRepository
            ->method('getById')
            ->willReturnCallback(
                static function (int $id, bool $editMode, int $storeId) use ($defaultProduct, $storeProduct) {
                    return $storeId === 0 ? $defaultProduct : $storeProduct;
                }
            );

        $result = $this->source->getOverrides('product', 5, ['code' => 'name']);

        self::assertFalse($result->isEmpty());
        self::assertSame(1, $result->count());

        $overrides = $result->getOverrides();
        self::assertSame(ScopeOverrideInterface::SCOPE_TYPE_STORES, $overrides[0]->getScopeType());
        self::assertSame('de', $overrides[0]->getScopeCode());
    }

    public function testGetOverridesSkipsUnchangedStoreValue(): void
    {
        $store = $this->createStore(2, 'de', 'German Store', 1);
        $this->storeManager->method('getStores')->willReturn([$store]);

        $product = $this->createProduct(5, 'name', 'Same Value');

        $this->productRepository->method('getById')->willReturn($product);

        $result = $this->source->getOverrides('product', 5, ['code' => 'name']);

        self::assertTrue($result->isEmpty());
    }

    public function testGetOverridesLogsWarningAndReturnsEmptyWhenStoreCountExceedsMax(): void
    {
        $stores = [
            $this->createStore(1, 's1', 'Store 1', 1),
            $this->createStore(2, 's2', 'Store 2', 1),
            $this->createStore(3, 's3', 'Store 3', 1),
        ];
        $this->storeManager->method('getStores')->willReturn($stores);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->with(EavProductOverrideSource::XML_PATH_MAX_STORES)
            ->willReturn(2);

        $this->logger->expects(self::once())->method('warning');

        $source = new EavProductOverrideSource(
            $this->productRepository,
            $this->storeManager,
            $scopeConfig,
            $this->escaper,
            $this->logger
        );

        $result = $source->getOverrides('product', 1, ['code' => 'name']);

        self::assertTrue($result->isEmpty());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @param mixed $value
     */
    private function createProduct(int $id, string $attributeCode, $value): ProductInterface
    {
        $product = $this->getMockBuilder(ProductInterface::class)
            ->disableOriginalConstructor()
            ->addMethods(['getData', 'getResource'])
            ->getMockForAbstractClass();
        $product->method('getId')->willReturn($id);
        $product->method('getData')
            ->with($attributeCode)
            ->willReturn($value);

        // getResource() → null; resolveDisplayValue() tries attribute source but catches Throwable
        $product->method('getResource')->willReturn(null);

        return $product;
    }

    private function createStore(int $id, string $code, string $name, int $websiteId): StoreInterface
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($id);
        $store->method('getCode')->willReturn($code);
        $store->method('getName')->willReturn($name);
        $store->method('getWebsiteId')->willReturn($websiteId);

        return $store;
    }

    private function createWebsite(int $id, string $code): WebsiteInterface
    {
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getId')->willReturn($id);
        $website->method('getCode')->willReturn($code);

        return $website;
    }
}
