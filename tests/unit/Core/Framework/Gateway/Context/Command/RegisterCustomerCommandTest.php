<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Gateway\Context\Command\RegisterCustomerCommand;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RegisterCustomerCommand::class)]
class RegisterCustomerCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = RegisterCustomerCommand::createFromPayload(['data' => ['foo' => 'bar']]);

        static::assertSame('context_register-customer', $command::getDefaultKeyName());
        static::assertSame(['foo' => 'bar'], $command->data);
    }
}
