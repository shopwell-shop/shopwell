<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Pagelet\Country;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Pagelet\Country\CountryRegionDataPagelet;
use Shopwell\Storefront\Pagelet\Country\CountryRegionDataPageletLoadedHook;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CountryRegionDataPageletLoadedHook::class)]
class CountryRegionDataPageletLoadedHookTest extends TestCase
{
    public function testGetName(): void
    {
        $hook = new CountryRegionDataPageletLoadedHook(new CountryRegionDataPagelet(), Generator::generateSalesChannelContext());

        static::assertSame('country-region-data-pagelet-loaded', $hook->getName());
        static::assertSame(CountryRegionDataPageletLoadedHook::HOOK_NAME, $hook->getName());
    }

    public function testGetPage(): void
    {
        $pagelet = new CountryRegionDataPagelet();

        $hook = new CountryRegionDataPageletLoadedHook($pagelet, Generator::generateSalesChannelContext());

        static::assertSame($pagelet, $hook->getPage());
    }

    public function testExposesThePageLoadedServices(): void
    {
        static::assertNotSame([], CountryRegionDataPageletLoadedHook::getServiceIds());
    }

    public function testCarriesSalesChannelContext(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $hook = new CountryRegionDataPageletLoadedHook(new CountryRegionDataPagelet(), $salesChannelContext);

        static::assertSame($salesChannelContext, $hook->getSalesChannelContext());
        static::assertSame($salesChannelContext->getContext(), $hook->getContext());
    }
}
