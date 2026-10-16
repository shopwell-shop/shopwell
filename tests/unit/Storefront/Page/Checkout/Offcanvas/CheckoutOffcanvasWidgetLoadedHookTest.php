<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Checkout\Offcanvas;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Page\Checkout\Offcanvas\CheckoutOffcanvasWidgetLoadedHook;
use Shopwell\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPage;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CheckoutOffcanvasWidgetLoadedHook::class)]
class CheckoutOffcanvasWidgetLoadedHookTest extends TestCase
{
    public function testNameRespectsCartSource(): void
    {
        $hook = $this->createHook();
        $hook->getCart()->setSource('test');

        static::assertStringEndsWith('-loaded-test', $hook->getName());
    }

    public function testNameWithoutCartSource(): void
    {
        static::assertStringEndsWith('-loaded', $this->createHook()->getName());
    }

    private function createHook(): CheckoutOffcanvasWidgetLoadedHook
    {
        return new CheckoutOffcanvasWidgetLoadedHook(
            (new OffcanvasCartPage())->assign(['cart' => new Cart(Uuid::randomHex())]),
            Generator::generateSalesChannelContext(),
        );
    }
}
