<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service;

use AvS\ScopeHint\Api\Data\ScopeOverrideInterface;
use AvS\ScopeHint\Service\OptionLabelResolver;
use AvS\ScopeHint\Service\ScopeOverrideDetector;
use AvS\ScopeHint\Service\ValueFormatter\Pool;
use AvS\ScopeHint\Service\ValueFormatter\ValueFormatterInterface;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Config\Model\ResourceModel\Config\Data\Collection;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\DataObject;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ScopeOverrideDetectorTest extends TestCase
{
    /** @var ScopeConfigInterface&MockObject */
    private $scopeConfig;

    /** @var WebsiteRepositoryInterface&MockObject */
    private $websiteRepository;

    /** @var StoreRepositoryInterface&MockObject */
    private $storeRepository;

    /** @var RequestInterface&MockObject */
    private $request;

    /** @var Escaper&MockObject */
    private $escaper;

    /** @var ScopeOverrideDetector */
    private $detector;

    /** @var array<int, DataObject> */
    private $configRecords = [];

    /** @var CollectionFactory&MockObject */
    private $collectionFactory;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->websiteRepository = $this->createMock(WebsiteRepositoryInterface::class);
        $this->storeRepository = $this->createMock(StoreRepositoryInterface::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->escaper = $this->createMock(Escaper::class);
        $this->escaper
            ->method('escapeHtml')
            ->willReturnCallback(static function ($v) {
                return is_string($v) ? htmlspecialchars($v, ENT_QUOTES) : (string) $v;
            });

        $defaultFormatter = $this->createMock(ValueFormatterInterface::class);
        $defaultFormatter->method('format')->willReturnCallback(static function ($value) {
            if (is_array($value)) {
                return '(complex value)';
            }
            return htmlspecialchars((string) $value, ENT_QUOTES);
        });
        $pool = $this->createMock(Pool::class);
        $pool->method('resolve')->willReturn($defaultFormatter);
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')
            ->with('scope', ['in' => ['websites', 'stores']])
            ->willReturnSelf();
        $collection->method('getIterator')->willReturnCallback(function () {
            return new \ArrayIterator($this->configRecords);
        });
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->detector = new ScopeOverrideDetector(
            $this->scopeConfig,
            $this->websiteRepository,
            $this->storeRepository,
            $this->request,
            $this->escaper,
            $pool,
            $this->collectionFactory
        );
    }

    public function testReturnsEmptyResultWhenNoOverridesExist(): void
    {
        $this->stubRequestParams(null, null);
        $this->websiteRepository->method('getList')->willReturn([]);
        $this->storeRepository->method('getList')->willReturn([]);
        $this->scopeConfig->method('getValue')->willReturn('value');

        $result = $this->detector->getOverrides('general/locale/code');

        self::assertTrue($result->isEmpty());
        self::assertSame(0, $result->count());
    }

    public function testDetectsWebsiteOverride(): void
    {
        $this->stubExplicitValue('general/locale/code', 'websites', 2, 'de_DE');
        $this->stubRequestParams(null, null);
        $website = $this->createWebsite(2, 'main_website', 'Main Website');
        $this->websiteRepository->method('getList')->willReturn([$website]);
        $this->websiteRepository->method('getById')->with(2)->willReturn($website);
        $this->storeRepository->method('getList')->willReturn([]);

        $this->scopeConfig
            ->method('getValue')
            ->willReturnMap([
                ['general/locale/code', null, null, 'en_US'],
                ['general/locale/code', 'websites', 2, 'de_DE'],
            ]);

        $result = $this->detector->getOverrides('general/locale/code');
        $overrides = $result->getOverrides();

        self::assertCount(1, $overrides);
        self::assertSame(ScopeOverrideInterface::SCOPE_TYPE_WEBSITES, $overrides[0]->getScopeType());
        self::assertSame('main_website', $overrides[0]->getScopeCode());
        self::assertSame('de_DE', $overrides[0]->getDisplayValue());
    }

    public function testDetectsStoreOverrideWithinSameWebsite(): void
    {
        $this->stubExplicitValue('payment/checkmo/order_status', 'stores', 5, 'processing');
        $this->stubRequestParams(null, null);
        $website = $this->createWebsite(2, 'main_website', 'Main Website');
        $store = $this->createStore(5, 'us_en', 'US Store', 2);
        $this->websiteRepository->method('getList')->willReturn([]);
        $this->websiteRepository->method('getById')->with(2)->willReturn($website);
        $this->storeRepository->method('getList')->willReturn([$store]);

        $this->scopeConfig
            ->method('getValue')
            ->willReturnMap([
                ['payment/checkmo/order_status', null, null, 'pending'],
                ['payment/checkmo/order_status', 'stores', 5, 'processing'],
            ]);

        $result = $this->detector->getOverrides('payment/checkmo/order_status');
        $overrides = $result->getOverrides();

        self::assertCount(1, $overrides);
        self::assertSame(ScopeOverrideInterface::SCOPE_TYPE_STORES, $overrides[0]->getScopeType());
        self::assertSame('us_en', $overrides[0]->getScopeCode());
        self::assertSame('main_website', $overrides[0]->getWebsiteCode());
    }

    public function testSkipsStoresOutsideCurrentWebsiteScope(): void
    {
        $this->stubRequestParams('main_website', null);
        $this->stubExplicitValue('general/locale/code', 'stores', 5, 'fr_FR');
        $this->stubExplicitValue('general/locale/code', 'stores', 6, 'de_DE');
        $matchingStore = $this->createStore(5, 'us_en', 'US Store', 2);
        $otherStore = $this->createStore(6, 'de_de', 'DE Store', 3);
        $website = $this->createWebsite(2, 'main_website', 'Main Website');
        $this->websiteRepository->method('getList')->willReturn([]);
        $this->websiteRepository->method('getById')->with(2)->willReturn($website);
        $this->storeRepository->method('getList')->willReturn([$matchingStore, $otherStore]);

        $this->scopeConfig
            ->method('getValue')
            ->willReturnMap([
                ['general/locale/code', 'website', 2, 'en_US'],
            ]);
        $this->websiteRepository->method('get')->with('main_website')->willReturn($website);

        $result = $this->detector->getOverrides('general/locale/code');

        self::assertCount(1, $result->getOverrides());
        self::assertSame('us_en', $result->getOverrides()[0]->getScopeCode());
    }

    public function testResolvesNumericWebsiteParamById(): void
    {
        $this->stubRequestParams('2', null);
        $this->stubExplicitValue('general/locale/code', 'stores', 5, 'fr_FR');
        $this->stubExplicitValue('general/locale/code', 'stores', 6, 'de_DE');
        $matchingStore = $this->createStore(5, 'us_en', 'US Store', 2);
        $otherStore = $this->createStore(6, 'de_de', 'DE Store', 3);
        $website = $this->createWebsite(2, 'main_website', 'Main Website');
        $this->websiteRepository->method('getList')->willReturn([]);
        $this->websiteRepository->method('getById')->with(2)->willReturn($website);
        $this->websiteRepository->expects(self::never())->method('get');
        $this->storeRepository->method('getList')->willReturn([$matchingStore, $otherStore]);

        $this->scopeConfig
            ->method('getValue')
            ->willReturnMap([
                ['general/locale/code', 'website', 2, 'en_US'],
            ]);

        $result = $this->detector->getOverrides('general/locale/code');

        self::assertCount(1, $result->getOverrides());
        self::assertSame('us_en', $result->getOverrides()[0]->getScopeCode());
        self::assertSame('main_website', $result->getOverrides()[0]->getWebsiteCode());
    }

    public function testFiltersOutCurrentStore(): void
    {
        $this->stubRequestParams(null, 'us_en');
        $store = $this->createStore(5, 'us_en', 'US Store', 2);
        $website = $this->createWebsite(2, 'main_website', 'Main Website');
        $this->websiteRepository->method('getList')->willReturn([]);
        $this->websiteRepository->method('getById')->with(2)->willReturn($website);
        $this->storeRepository->method('getList')->willReturn([$store]);
        $this->scopeConfig->method('getValue')->willReturn('value');

        $result = $this->detector->getOverrides('general/locale/code');

        self::assertTrue($result->isEmpty());
    }

    public function testArrayValuesAreFlaggedAsComplex(): void
    {
        $this->stubExplicitValue('catalog/category/path/whatever', 'websites', 2, ['b' => 2]);
        $this->stubRequestParams(null, null);
        $website = $this->createWebsite(2, 'main_website', 'Main Website');
        $this->websiteRepository->method('getList')->willReturn([$website]);
        $this->websiteRepository->method('getById')->willReturn($website);
        $this->storeRepository->method('getList')->willReturn([]);

        $this->scopeConfig
            ->method('getValue')
            ->willReturnMap([
                ['catalog/category/path/whatever', null, null, ['a' => 1]],
                ['catalog/category/path/whatever', 'websites', 2, ['b' => 2]],
            ]);

        $result = $this->detector->getOverrides('catalog/category/path/whatever');

        self::assertCount(1, $result->getOverrides());
        self::assertSame('(complex value)', $result->getOverrides()[0]->getDisplayValue());
    }

    public function testGroupingByWebsite(): void
    {
        $this->stubExplicitValue('x/y/z', 'stores', 5, 'b');
        $this->stubExplicitValue('x/y/z', 'stores', 6, 'c');
        $this->stubRequestParams(null, null);
        $website2 = $this->createWebsite(2, 'main_website', 'Main Website');
        $store5 = $this->createStore(5, 'us_en', 'US Store', 2);
        $store6 = $this->createStore(6, 'us_es', 'US Spanish', 2);

        $this->websiteRepository->method('getList')->willReturn([]);
        $this->websiteRepository->method('getById')->willReturn($website2);
        $this->storeRepository->method('getList')->willReturn([$store5, $store6]);

        $this->scopeConfig
            ->method('getValue')
            ->willReturnMap([
                ['x/y/z', null, null, 'a'],
                ['x/y/z', 'stores', 5, 'b'],
                ['x/y/z', 'stores', 6, 'c'],
            ]);

        $result = $this->detector->getOverrides('x/y/z');
        $grouped = $result->getOverridesByWebsite();

        self::assertArrayHasKey('main_website', $grouped);
        self::assertCount(2, $grouped['main_website']);
    }

    private function stubRequestParams(?string $website, ?string $store): void
    {
        $this->request
            ->method('getParam')
            ->willReturnCallback(static function ($key) use ($website, $store) {
                if ($key === 'website') {
                    return $website;
                }
                if ($key === 'store') {
                    return $store;
                }
                return null;
            });
    }

    /** @param mixed $value */
    public function testLoadsExplicitValuesOnceForAllPaths(): void
    {
        $this->collectionFactory->expects(self::once())->method('create');
        $this->stubExplicitValue('general/locale/code', 'websites', 2, 'de_DE');
        $this->stubExplicitValue('general/store_information/name', 'websites', 2, 'Shop DE');
        $this->stubRequestParams(null, null);
        $website = $this->createWebsite(2, 'main_website', 'Main Website');
        $this->websiteRepository->method('getList')->willReturn([$website]);
        $this->storeRepository->method('getList')->willReturn([]);
        $this->scopeConfig->method('getValue')->willReturn('default');

        $localeOverrides = $this->detector->getOverrides('general/locale/code')->getOverrides();
        $nameOverrides = $this->detector->getOverrides('general/store_information/name')->getOverrides();
        $untouchedOverrides = $this->detector->getOverrides('general/country/default')->getOverrides();

        self::assertSame('de_DE', $localeOverrides[0]->getDisplayValue());
        self::assertSame('Shop DE', $nameOverrides[0]->getDisplayValue());
        self::assertSame([], $untouchedOverrides);
    }

    /**
     * @param mixed $value
     */
    private function stubExplicitValue(string $path, string $scope, int $scopeId, $value): void
    {
        $this->configRecords[] = new DataObject([
            'path' => $path,
            'scope' => $scope,
            'scope_id' => $scopeId,
            'value' => $value,
        ]);
    }

    private function createWebsite(int $id, string $code, string $name): WebsiteInterface
    {
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getId')->willReturn($id);
        $website->method('getCode')->willReturn($code);
        $website->method('getName')->willReturn($name);
        return $website;
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
}
