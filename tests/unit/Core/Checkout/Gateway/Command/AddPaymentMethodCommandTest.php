<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Gateway\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Gateway\Command\AddPaymentMethodCommand;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AddPaymentMethodCommand::class)]
class AddPaymentMethodCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = new AddPaymentMethodCommand('test');

        static::assertSame('test', $command->paymentMethodTechnicalName);
    }

    public function testCommandKey(): void
    {
        static::assertSame(AddPaymentMethodCommand::COMMAND_KEY, AddPaymentMethodCommand::getDefaultKeyName());
    }
}
