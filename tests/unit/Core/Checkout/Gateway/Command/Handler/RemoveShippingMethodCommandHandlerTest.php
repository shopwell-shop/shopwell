<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Gateway\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Error\ErrorCollection;
use Shopwell\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopwell\Core\Checkout\Gateway\Command\Handler\RemoveShippingMethodCommandHandler;
use Shopwell\Core\Checkout\Gateway\Command\RemoveShippingMethodCommand;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(RemoveShippingMethodCommandHandler::class)]
class RemoveShippingMethodCommandHandlerTest extends TestCase
{
    public function testSupportedCommands(): void
    {
        static::assertSame(
            [RemoveShippingMethodCommand::class],
            RemoveShippingMethodCommandHandler::supportedCommands()
        );
    }

    public function testHandle(): void
    {
        $shippingMethod1 = new ShippingMethodEntity();
        $shippingMethod1->setUniqueIdentifier(Uuid::randomHex());
        $shippingMethod1->setTechnicalName('test-1');

        $shippingMethod2 = new ShippingMethodEntity();
        $shippingMethod2->setUniqueIdentifier(Uuid::randomHex());
        $shippingMethod2->setTechnicalName('test-2');

        $shippingMethods = new ShippingMethodCollection([$shippingMethod1, $shippingMethod2]);

        $response = new CheckoutGatewayResponse(
            new PaymentMethodCollection(),
            $shippingMethods,
            new ErrorCollection()
        );

        $command = new RemoveShippingMethodCommand('test-1');

        $handler = new RemoveShippingMethodCommandHandler();
        $handler->handle($command, $response, Generator::generateSalesChannelContext());

        static::assertCount(1, $response->getAvailableShippingMethods());
        static::assertNotNull($response->getAvailableShippingMethods()->first());
        static::assertSame('test-2', $response->getAvailableShippingMethods()->first()->getTechnicalName());
    }
}
