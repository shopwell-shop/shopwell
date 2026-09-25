<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Shipping\Hook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Shipping\Hook\ShippingMethodRouteHook;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ShippingMethodRouteHook::class)]
class ShippingMethodRouteHookTest extends TestCase
{
    public function testConstruct(): void
    {
        $hook = new ShippingMethodRouteHook(
            $collection = new ShippingMethodCollection(),
            $onlyAvailable = true,
            $salesChannelContext = Generator::generateSalesChannelContext()
        );

        static::assertSame($collection, $hook->getCollection());
        static::assertSame($onlyAvailable, $hook->isOnlyAvailable());
        static::assertSame($salesChannelContext, $hook->getSalesChannelContext());

        static::assertSame('shipping-method-route-request', $hook->getName());
    }
}
