<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service;

use AvS\ScopeHint\Service\ScopeOverrideDetector;
use AvS\ScopeHint\Service\ValueFormatter\ParentAwareValueFormatterInterface;
use AvS\ScopeHint\Service\ValueFormatter\Pool;
use AvS\ScopeHint\Service\ValueFormatter\ValueFormatterInterface;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Config\Model\ResourceModel\Config\Data\Collection;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ScopeOverrideDetectorGapTest extends TestCase
{
    private const PATH = 'carriers/custom/rows';

    /** @var ScopeConfigInterface&MockObject */
    private $scopeConfig;

    /** @var WebsiteRepositoryInterface&MockObject */
    private $websiteRepository;

    /** @var StoreRepositoryInterface&MockObject */
    private $storeRepository;

    /** @var RequestInterface&MockObject */
    private $request;

    /** @var Pool&MockObject */
    private $pool;

    /** @var Field&MockObject */
    private $field;

    /** @var array<int, DataObject> */
    private $configRecords = [];

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->websiteRepository = $this->createMock(WebsiteRepositoryInterface::class);
        $this->storeRepository = $this->createMock(StoreRepositoryInterface::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->pool = $this->createMock(Pool::class);
        $this->field = $this->createMock(Field::class);
    }

    public function testWebsiteOverrideGivesParentAwareFormatterTheDefaultValue(): void
    {
        $this->stubExplicitValue('websites', 2, 'website-value');
        $this->stubRequestParams(null, null);
        $this->stubWebsites([$this->createWebsite(2, 'main')]);
        $this->storeRepository->method('getList')->willReturn([]);
        $this->scopeConfig->method('getValue')->willReturnMap([
            [self::PATH, 'default', null, 'default-value'],
            [self::PATH, null, null, 'default-value'],
        ]);

        $formatter = $this->createMock(ParentAwareValueFormatterInterface::class);
        $formatter->expects(self::once())
            ->method('formatAgainstParent')
            ->with('website-value', 'default-value', $this->field)
            ->willReturn('diff');
        $formatter->expects(self::never())->method('format');
        $this->pool->method('resolve')->willReturn($formatter);

        $overrides = $this->createDetector()->getOverrides(self::PATH, $this->field)->getOverrides();

        self::assertSame('diff', $overrides[0]->getDisplayValue());
    }

    public function testStoreOverrideAtDefaultScopeGivesParentAwareFormatterTheWebsiteValue(): void
    {
        $this->stubExplicitValue('stores', 5, 'store-value');
        $this->stubRequestParams(null, null);
        $this->stubWebsites([$this->createWebsite(2, 'main')]);
        $this->storeRepository->method('getList')->willReturn([$this->createStore(5, 'us', 2)]);
        $this->scopeConfig->method('getValue')->willReturnMap([
            [self::PATH, null, null, 'default-value'],
            [self::PATH, 'website', 2, 'inherited-website-value'],
        ]);

        $formatter = $this->createMock(ParentAwareValueFormatterInterface::class);
        $formatter->expects(self::once())
            ->method('formatAgainstParent')
            ->with('store-value', 'inherited-website-value', $this->field)
            ->willReturn('diff');
        $this->pool->method('resolve')->willReturn($formatter);

        $overrides = $this->createDetector()->getOverrides(self::PATH, $this->field)->getOverrides();

        self::assertCount(1, $overrides);
        self::assertSame('diff', $overrides[0]->getDisplayValue());
    }

    public function testStoreOverrideAtWebsiteScopeGivesParentAwareFormatterTheCurrentWebsiteValue(): void
    {
        $this->stubExplicitValue('stores', 5, 'store-value');
        $this->stubRequestParams('main', null);
        $website = $this->createWebsite(2, 'main');
        $this->websiteRepository->method('get')->with('main')->willReturn($website);
        $this->websiteRepository->method('getById')->with(2)->willReturn($website);
        $this->storeRepository->method('getList')->willReturn([$this->createStore(5, 'us', 2)]);
        $this->scopeConfig->method('getValue')->willReturnMap([
            [self::PATH, 'website', 2, 'current-website-value'],
        ]);

        $formatter = $this->createMock(ParentAwareValueFormatterInterface::class);
        $formatter->expects(self::once())
            ->method('formatAgainstParent')
            ->with('store-value', 'current-website-value', $this->field)
            ->willReturn('diff');
        $this->pool->method('resolve')->willReturn($formatter);

        $overrides = $this->createDetector()->getOverrides(self::PATH, $this->field)->getOverrides();

        self::assertSame('diff', $overrides[0]->getDisplayValue());
    }

    public function testPlainFormatterStillReceivesFormatOnly(): void
    {
        $this->stubExplicitValue('websites', 2, 'website-value');
        $this->stubRequestParams(null, null);
        $this->stubWebsites([$this->createWebsite(2, 'main')]);
        $this->storeRepository->method('getList')->willReturn([]);
        $this->scopeConfig->method('getValue')->willReturn('default-value');

        $formatter = $this->createMock(ValueFormatterInterface::class);
        $formatter->expects(self::once())
            ->method('format')
            ->with('website-value', $this->field)
            ->willReturn('plain');
        $this->pool->method('resolve')->willReturn($formatter);

        $overrides = $this->createDetector()->getOverrides(self::PATH, $this->field)->getOverrides();

        self::assertSame('plain', $overrides[0]->getDisplayValue());
    }

    public function testParentAwareFormatterIsNotUsedWithoutFieldContext(): void
    {
        $this->stubExplicitValue('websites', 2, 'website-value');
        $this->stubRequestParams(null, null);
        $this->stubWebsites([$this->createWebsite(2, 'main')]);
        $this->storeRepository->method('getList')->willReturn([]);
        $this->scopeConfig->method('getValue')->willReturn('default-value');
        $this->pool->expects(self::never())->method('resolve');

        $overrides = $this->createDetector()->getOverrides(self::PATH)->getOverrides();

        self::assertSame('website-value', $overrides[0]->getDisplayValue());
    }

    private function createDetector(): ScopeOverrideDetector
    {
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(
            static function ($value) {
                return htmlspecialchars((string) $value, ENT_QUOTES);
            }
        );
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturnCallback(function () {
            return new \ArrayIterator($this->configRecords);
        });
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        return new ScopeOverrideDetector(
            $this->scopeConfig,
            $this->websiteRepository,
            $this->storeRepository,
            $this->request,
            $escaper,
            $this->pool,
            $collectionFactory
        );
    }

    private function stubRequestParams(?string $website, ?string $store): void
    {
        $this->request->method('getParam')->willReturnMap([
            ['website', null, $website],
            ['store', null, $store],
        ]);
    }

    /**
     * @param WebsiteInterface[] $websites
     */
    private function stubWebsites(array $websites): void
    {
        $this->websiteRepository->method('getList')->willReturn($websites);
        $this->websiteRepository->method('getById')->willReturn($websites[0]);
    }

    /**
     * @param mixed $value
     */
    private function stubExplicitValue(string $scope, int $scopeId, $value): void
    {
        $this->configRecords[] = new DataObject([
            'path' => self::PATH,
            'scope' => $scope,
            'scope_id' => $scopeId,
            'value' => $value,
        ]);
    }

    private function createWebsite(int $id, string $code): WebsiteInterface
    {
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getId')->willReturn($id);
        $website->method('getCode')->willReturn($code);
        $website->method('getName')->willReturn($code);

        return $website;
    }

    private function createStore(int $id, string $code, int $websiteId): StoreInterface
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($id);
        $store->method('getCode')->willReturn($code);
        $store->method('getName')->willReturn($code);
        $store->method('getWebsiteId')->willReturn($websiteId);

        return $store;
    }
}
