<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Hook\CartAware;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Page\Checkout\Cart\CheckoutCartPage;
use Shopwell\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedHook;
use Shopwell\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopwell\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedHook;
use Shopwell\Storefront\Page\Checkout\Offcanvas\CheckoutInfoWidgetLoadedHook;
use Shopwell\Storefront\Page\Checkout\Offcanvas\CheckoutOffcanvasWidgetLoadedHook;
use Shopwell\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPage;
use Shopwell\Storefront\Page\Checkout\Register\CheckoutRegisterPage;
use Shopwell\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoadedHook;
use Shopwell\Storefront\Page\PageLoadedHook;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CheckoutCartPageLoadedHook::class)]
#[CoversClass(CheckoutConfirmPageLoadedHook::class)]
#[CoversClass(CheckoutInfoWidgetLoadedHook::class)]
#[CoversClass(CheckoutOffcanvasWidgetLoadedHook::class)]
#[CoversClass(CheckoutRegisterPageLoadedHook::class)]
class CheckoutPageLoadedHookTest extends TestCase
{
    /**
     * @return array<array<PageLoadedHook&CartAware>>
     */
    public static function dataProviderHooks(): array
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        return [
            [new CheckoutCartPageLoadedHook((new CheckoutCartPage())->assign(['cart' => new Cart(Uuid::randomHex())]), $salesChannelContext)],
            [new CheckoutConfirmPageLoadedHook((new CheckoutConfirmPage())->assign(['cart' => new Cart(Uuid::randomHex())]), $salesChannelContext)],
            [new CheckoutInfoWidgetLoadedHook((new OffcanvasCartPage())->assign(['cart' => new Cart(Uuid::randomHex())]), $salesChannelContext)],
            [new CheckoutOffcanvasWidgetLoadedHook((new OffcanvasCartPage())->assign(['cart' => new Cart(Uuid::randomHex())]), $salesChannelContext)],
            [new CheckoutRegisterPageLoadedHook((new CheckoutRegisterPage())->assign(['cart' => new Cart(Uuid::randomHex())]), $salesChannelContext)],
        ];
    }

    #[DataProvider('dataProviderHooks')]
    public function testNameRespectsCartSource(PageLoadedHook&CartAware $hook): void
    {
        $hook->getCart()->setSource('test');

        static::assertStringEndsWith('-loaded-test', $hook->getName());
    }

    #[DataProvider('dataProviderHooks')]
    public function testNameWithoutCartSource(PageLoadedHook&CartAware $hook): void
    {
        static::assertStringEndsWith('-loaded', $hook->getName());
    }
}
