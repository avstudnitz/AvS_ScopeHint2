<?php

declare(strict_types=1);

namespace AvS\ScopeHint\Test\Unit\Service;

use AvS\ScopeHint\Api\Data\ScopeOverrideInterface;
use AvS\ScopeHint\Service\OptionLabelResolver;
use AvS\ScopeHint\Service\ScopeOverrideDetector;
use Magento\Config\Model\Config\Structure\Element\Field;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
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

        $this->detector = new ScopeOverrideDetector(
            $this->scopeConfig,
            $this->websiteRepository,
            $this->storeRepository,
            $this->request,
            $this->escaper,
            new OptionLabelResolver()
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
        $this->stubRequestParams('2', null);
        $matchingStore = $this->createStore(5, 'us_en', 'US Store', 2);
        $otherStore = $this->createStore(6, 'de_de', 'DE Store', 3);
        $website = $this->createWebsite(2, 'main_website', 'Main Website');
        $this->websiteRepository->method('getList')->willReturn([]);
        $this->websiteRepository->method('getById')->with(2)->willReturn($website);
        $this->storeRepository->method('getList')->willReturn([$matchingStore, $otherStore]);

        $this->scopeConfig
            ->method('getValue')
            ->willReturnMap([
                ['general/locale/code', 'website', '2', 'en_US'],
                ['general/locale/code', 'stores', 5, 'fr_FR'],
                ['general/locale/code', 'stores', 6, 'de_DE'],
            ]);

        $result = $this->detector->getOverrides('general/locale/code');

        self::assertCount(1, $result->getOverrides());
        self::assertSame('us_en', $result->getOverrides()[0]->getScopeCode());
    }

    public function testFiltersOutCurrentStore(): void
    {
        $this->stubRequestParams(null, '5');
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
