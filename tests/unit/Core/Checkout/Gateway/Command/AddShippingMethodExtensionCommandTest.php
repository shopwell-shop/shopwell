<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Gateway\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Gateway\Command\AddShippingMethodExtensionCommand;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AddShippingMethodExtensionCommand::class)]
class AddShippingMethodExtensionCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = new AddShippingMethodExtensionCommand('test', 'foo', ['foo' => 'bar', 1 => 2]);

        static::assertSame('test', $command->shippingMethodTechnicalName);
        static::assertSame('foo', $command->extensionKey);
        static::assertSame(['foo' => 'bar', 1 => 2], $command->extensionsPayload);
    }

    public function testCommandKey(): void
    {
        static::assertSame(AddShippingMethodExtensionCommand::COMMAND_KEY, AddShippingMethodExtensionCommand::getDefaultKeyName());
    }
}
