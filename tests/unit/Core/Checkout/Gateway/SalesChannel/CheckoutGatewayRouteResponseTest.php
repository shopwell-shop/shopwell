<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Gateway\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Error\ErrorCollection;
use Shopwell\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CheckoutGatewayRouteResponse::class)]
class CheckoutGatewayRouteResponseTest extends TestCase
{
    public function testConstruct(): void
    {
        $response = new CheckoutGatewayRouteResponse(
            $payments = new PaymentMethodCollection(),
            $shipments = new ShippingMethodCollection(),
            $errors = new ErrorCollection()
        );

        static::assertSame($payments, $response->getPaymentMethods());
        static::assertSame($shipments, $response->getShippingMethods());
        static::assertSame($errors, $response->getErrors());
    }

    public function testSetters(): void
    {
        $response = new CheckoutGatewayRouteResponse(
            new PaymentMethodCollection(),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $response->setPaymentMethods($newPayments = new PaymentMethodCollection());
        $response->setShippingMethods($newShipments = new ShippingMethodCollection());
        $response->setErrors($newErrors = new ErrorCollection());

        static::assertSame($newPayments, $response->getPaymentMethods());
        static::assertSame($newShipments, $response->getShippingMethods());
        static::assertSame($newErrors, $response->getErrors());
    }
}
