<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Gateway\Context\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Gateway\Context\Command\ChangeBillingAddressCommand;
use Shopwell\Core\Framework\Gateway\Context\Command\ChangeShippingAddressCommand;
use Shopwell\Core\Framework\Gateway\Context\Command\Handler\ChangeAddressCommandHandler;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ChangeAddressCommandHandler::class)]
class ChangeAddressCommandHandlerTest extends TestCase
{
    public function testHandleBillingAddressCommand(): void
    {
        $command = ChangeBillingAddressCommand::createFromPayload(['addressId' => 'billingAddressId']);
        $context = Generator::generateSalesChannelContext();
        $parameters = [];

        $handler = new ChangeAddressCommandHandler();

        $handler->handle($command, $context, $parameters);

        static::assertSame(['billingAddressId' => 'billingAddressId'], $parameters);
    }

    public function testHandleShippingAddressCommand(): void
    {
        $command = ChangeShippingAddressCommand::createFromPayload(['addressId' => 'shippingAddressId']);
        $context = Generator::generateSalesChannelContext();
        $parameters = [];

        $handler = new ChangeAddressCommandHandler();

        $handler->handle($command, $context, $parameters);

        static::assertSame(['shippingAddressId' => 'shippingAddressId'], $parameters);
    }

    public function testGetSupportedCommands(): void
    {
        static::assertSame([
            ChangeBillingAddressCommand::class,
            ChangeShippingAddressCommand::class,
        ], ChangeAddressCommandHandler::supportedCommands());
    }
}
