<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Gateway\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Error\ErrorCollection;
use Shopwell\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopwell\Core\Checkout\Gateway\Command\Handler\RemovePaymentMethodCommandHandler;
use Shopwell\Core\Checkout\Gateway\Command\RemovePaymentMethodCommand;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(RemovePaymentMethodCommandHandler::class)]
class RemovePaymentMethodCommandHandlerTest extends TestCase
{
    public function testSupportedCommands(): void
    {
        static::assertSame(
            [RemovePaymentMethodCommand::class],
            RemovePaymentMethodCommandHandler::supportedCommands()
        );
    }

    public function testHandle(): void
    {
        $paymentMethod1 = new PaymentMethodEntity();
        $paymentMethod1->setUniqueIdentifier(Uuid::randomHex());
        $paymentMethod1->setTechnicalName('test-1');

        $paymentMethod2 = new PaymentMethodEntity();
        $paymentMethod2->setUniqueIdentifier(Uuid::randomHex());
        $paymentMethod2->setTechnicalName('test-2');

        $paymentMethods = new PaymentMethodCollection([$paymentMethod1, $paymentMethod2]);

        $response = new CheckoutGatewayResponse(
            $paymentMethods,
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $command = new RemovePaymentMethodCommand('test-1');

        $handler = new RemovePaymentMethodCommandHandler();
        $handler->handle($command, $response, Generator::generateSalesChannelContext());

        static::assertCount(1, $response->getAvailablePaymentMethods());
        static::assertNotNull($response->getAvailablePaymentMethods()->first());
        static::assertSame('test-2', $response->getAvailablePaymentMethods()->first()->getTechnicalName());
    }
}
