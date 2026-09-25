<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Gateway\Command\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Gateway\Command\Struct\CheckoutGatewayPayloadStruct;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CheckoutGatewayPayloadStruct::class)]
class CheckoutGatewayPayloadStructTest extends TestCase
{
    public function testConstruct(): void
    {
        $cart = new Cart('test');
        $context = Generator::generateSalesChannelContext();
        $paymentMethods = new PaymentMethodCollection();
        $shippingMethods = new ShippingMethodCollection();

        $struct = new CheckoutGatewayPayloadStruct($cart, $context, $paymentMethods, $shippingMethods);

        static::assertSame($cart, $struct->getCart());
        static::assertSame($context, $struct->getSalesChannelContext());
        static::assertSame($paymentMethods, $struct->getPaymentMethods());
        static::assertSame($shippingMethods, $struct->getShippingMethods());
    }
}
