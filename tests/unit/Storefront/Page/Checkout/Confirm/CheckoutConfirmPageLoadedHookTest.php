<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Checkout\Confirm;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopwell\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedHook;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CheckoutConfirmPageLoadedHook::class)]
class CheckoutConfirmPageLoadedHookTest extends TestCase
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

    private function createHook(): CheckoutConfirmPageLoadedHook
    {
        return new CheckoutConfirmPageLoadedHook(
            (new CheckoutConfirmPage())->assign(['cart' => new Cart(Uuid::randomHex())]),
            Generator::generateSalesChannelContext(),
        );
    }
}
