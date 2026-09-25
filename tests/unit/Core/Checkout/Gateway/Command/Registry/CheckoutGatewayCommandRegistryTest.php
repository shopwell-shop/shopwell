<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Gateway\Command\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Gateway\Command\Registry\CheckoutGatewayCommandRegistry;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Tests\Unit\Core\Checkout\Gateway\Command\_fixture\StubCheckoutGatewayCommand;
use Shopwell\Tests\Unit\Core\Checkout\Gateway\Command\_fixture\StubCheckoutGatewayFooCommand;
use Shopwell\Tests\Unit\Core\Checkout\Gateway\Command\_fixture\StubCheckoutGatewayHandler;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CheckoutGatewayCommandRegistry::class)]
class CheckoutGatewayCommandRegistryTest extends TestCase
{
    public function testConstruct(): void
    {
        $handler = new StubCheckoutGatewayHandler();
        $registry = new CheckoutGatewayCommandRegistry([$handler]);

        static::assertTrue($registry->has(StubCheckoutGatewayCommand::COMMAND_KEY));
        static::assertTrue($registry->has(StubCheckoutGatewayFooCommand::COMMAND_KEY));
        static::assertFalse($registry->has('not-existing-key'));

        static::assertSame($handler, $registry->get(StubCheckoutGatewayCommand::COMMAND_KEY));
        static::assertSame($handler, $registry->get(StubCheckoutGatewayFooCommand::COMMAND_KEY));

        static::assertTrue($registry->hasAppCommand(StubCheckoutGatewayCommand::COMMAND_KEY));
        static::assertTrue($registry->hasAppCommand(StubCheckoutGatewayFooCommand::COMMAND_KEY));
        static::assertFalse($registry->hasAppCommand('not-existing-key'));

        static::assertSame(StubCheckoutGatewayCommand::class, $registry->getAppCommand(StubCheckoutGatewayCommand::COMMAND_KEY));
        static::assertSame(StubCheckoutGatewayFooCommand::class, $registry->getAppCommand(StubCheckoutGatewayFooCommand::COMMAND_KEY));
    }

    public function testAll(): void
    {
        $handler = new StubCheckoutGatewayHandler();
        $registry = new CheckoutGatewayCommandRegistry([$handler]);

        static::assertSame(
            [
                StubCheckoutGatewayCommand::COMMAND_KEY => $handler,
                StubCheckoutGatewayFooCommand::COMMAND_KEY => $handler,
            ],
            $registry->all()
        );
    }
}
