<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service;

use AvS\ScopeHint\Api\Data\ScopeOverrideInterface;
use AvS\ScopeHint\Service\EavScopeOverrideDetector;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as EavAttribute;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class EavScopeOverrideDetectorTest extends TestCase
{
    /** @var StoreManagerInterface&MockObject */
    private $storeManager;

    /** @var CategoryRepositoryInterface&MockObject */
    private $categoryRepository;

    /** @var ScopeConfigInterface&MockObject */
    private $scopeConfig;

    /** @var Escaper&MockObject */
    private $escaper;

    /** @var LoggerInterface&MockObject */
    private $logger;

    /** @var EavScopeOverrideDetector */
    private $detector;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->escaper = $this->createMock(Escaper::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->escaper
            ->method('escapeHtml')
            ->willReturnCallback(static function ($v) {
                return is_string($v) ? htmlspecialchars($v, ENT_QUOTES) : (string) $v;
            });

        // Default: max_stores = 50 (well above our test store counts)
        $this->scopeConfig
            ->method('getValue')
            ->with(EavScopeOverrideDetector::XML_PATH_MAX_STORES)
            ->willReturn(50);

        $this->detector = new EavScopeOverrideDetector(
            $this->storeManager,
            $this->categoryRepository,
            $this->scopeConfig,
            $this->escaper,
            $this->logger
        );
    }

    public function testReturnsEmptyWhenNoStoresExist(): void
    {
        $this->storeManager->method('getStores')->willReturn([]);

        $attribute = $this->createAttribute('name', 'text');
        $currentCategory = $this->createCategory(42, 'name', 'Root Category');
        $this->categoryRepository
            ->method('get')
            ->willReturn($currentCategory);

        $result = $this->detector->getCategoryOverrides(42, $attribute, 1);

        self::assertTrue($result->isEmpty());
    }

    public function testDetectsOverriddenNameInStoreView(): void
    {
        $store2 = $this->createStore(2, 'de', 'German Store', 1);
        $store3 = $this->createStore(3, 'fr', 'French Store', 1);

        $this->storeManager->method('getStores')->willReturn([$store2, $store3]);

        $website = $this->createWebsite(1, 'base');
        $this->storeManager->method('getWebsite')->with(1)->willReturn($website);

        $attribute = $this->createAttribute('name', 'text');

        $defaultCategory = $this->createCategory(10, 'name', 'Shoes');
        $deCategory = $this->createCategory(10, 'name', 'Schuhe');
        $frCategory = $this->createCategory(10, 'name', 'Shoes'); // same as default

        $this->categoryRepository
            ->method('get')
            ->willReturnMap([
                [10, 1, $defaultCategory],
                [10, 2, $deCategory],
                [10, 3, $frCategory],
            ]);

        $result = $this->detector->getCategoryOverrides(10, $attribute, 1);

        self::assertFalse($result->isEmpty());
        self::assertSame(1, $result->count());

        $overrides = $result->getOverrides();
        self::assertSame(ScopeOverrideInterface::SCOPE_TYPE_STORES, $overrides[0]->getScopeType());
        self::assertSame('de', $overrides[0]->getScopeCode());
        self::assertSame('Schuhe', $overrides[0]->getDisplayValue());
    }

    public function testSkipsCurrentStoreFromComparison(): void
    {
        $store2 = $this->createStore(2, 'de', 'German Store', 1);

        $this->storeManager->method('getStores')->willReturn([$store2]);

        $website = $this->createWebsite(1, 'base');
        $this->storeManager->method('getWebsite')->with(1)->willReturn($website);

        $attribute = $this->createAttribute('name', 'text');

        $defaultCategory = $this->createCategory(10, 'name', 'Shoes');

        $this->categoryRepository
            ->method('get')
            ->willReturnMap([
                [10, 1, $defaultCategory],
                [10, 2, $defaultCategory],
            ]);

        // currentStoreId = 2 — should be skipped
        $result = $this->detector->getCategoryOverrides(10, $attribute, 2);

        self::assertTrue($result->isEmpty());
    }

    public function testSkipsDetectionWhenStoreCountExceedsMax(): void
    {
        // Build 3 stores and set max to 2
        $stores = [
            $this->createStore(1, 's1', 'Store 1', 1),
            $this->createStore(2, 's2', 'Store 2', 1),
            $this->createStore(3, 's3', 'Store 3', 1),
        ];
        $this->storeManager->method('getStores')->willReturn($stores);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->with(EavScopeOverrideDetector::XML_PATH_MAX_STORES)
            ->willReturn(2);

        $this->logger->expects(self::once())->method('warning');

        $detector = new EavScopeOverrideDetector(
            $this->storeManager,
            $this->categoryRepository,
            $scopeConfig,
            $this->escaper,
            $this->logger
        );

        $attribute = $this->createAttribute('name', 'text');
        $result = $detector->getCategoryOverrides(10, $attribute, 1);

        self::assertTrue($result->isEmpty());
    }

    public function testReturnsEmptyWhenCategoryNotFound(): void
    {
        $store2 = $this->createStore(2, 'de', 'German Store', 1);
        $this->storeManager->method('getStores')->willReturn([$store2]);

        $this->categoryRepository
            ->method('get')
            ->willThrowException(new NoSuchEntityException());

        $attribute = $this->createAttribute('name', 'text');
        $result = $this->detector->getCategoryOverrides(99, $attribute, 1);

        self::assertTrue($result->isEmpty());
    }

    public function testSelectAttributeResolvesOptionLabel(): void
    {
        $store2 = $this->createStore(2, 'de', 'German Store', 1);
        $this->storeManager->method('getStores')->willReturn([$store2]);

        $website = $this->createWebsite(1, 'base');
        $this->storeManager->method('getWebsite')->with(1)->willReturn($website);

        // Attribute with select input
        $source = $this->createMock(\Magento\Eav\Model\Entity\Attribute\Source\AbstractSource::class);
        $source->method('getOptionText')->with('2')->willReturn('Active');

        $attribute = $this->getMockBuilder(EavAttribute::class)
            ->disableOriginalConstructor()
            ->getMock();
        $attribute->method('getAttributeCode')->willReturn('is_active');
        $attribute->method('getFrontendInput')->willReturn('select');
        $attribute->method('isScopeGlobal')->willReturn(false);
        $attribute->method('getSource')->willReturn($source);

        $defaultCategory = $this->createCategory(5, 'is_active', '1');
        $deCategory = $this->createCategory(5, 'is_active', '2');

        $this->categoryRepository
            ->method('get')
            ->willReturnMap([
                [5, 1, $defaultCategory],
                [5, 2, $deCategory],
            ]);

        $result = $this->detector->getCategoryOverrides(5, $attribute, 1);

        self::assertSame(1, $result->count());
        self::assertSame('Active', $result->getOverrides()[0]->getDisplayValue());
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

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

    /**
     * @param mixed $value
     */
    private function createCategory(int $id, string $attributeCode, $value): CategoryInterface
    {
        $category = $this->getMockBuilder(CategoryInterface::class)
            ->disableOriginalConstructor()
            ->addMethods(['getData'])
            ->getMockForAbstractClass();
        $category->method('getId')->willReturn($id);
        $category->method('getData')
            ->with($attributeCode)
            ->willReturn($value);

        return $category;
    }

    private function createAttribute(string $code, string $frontendInput): EavAttribute
    {
        $attribute = $this->getMockBuilder(EavAttribute::class)
            ->disableOriginalConstructor()
            ->getMock();
        $attribute->method('getAttributeCode')->willReturn($code);
        $attribute->method('getFrontendInput')->willReturn($frontendInput);
        $attribute->method('isScopeGlobal')->willReturn(false);

        return $attribute;
    }
}
