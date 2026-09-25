<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Gateway\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Gateway\Command\RemovePaymentMethodCommand;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(RemovePaymentMethodCommand::class)]
class RemovePaymentMethodCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = new RemovePaymentMethodCommand('test');

        static::assertSame('test', $command->paymentMethodTechnicalName);
    }

    public function testCommandKey(): void
    {
        static::assertSame(RemovePaymentMethodCommand::COMMAND_KEY, RemovePaymentMethodCommand::getDefaultKeyName());
    }
}
