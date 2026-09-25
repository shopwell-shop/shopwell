<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Gateway\Context\Command\AddCustomerMessageCommand;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AddCustomerMessageCommand::class)]
class AddCustomerMessageCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = AddCustomerMessageCommand::createFromPayload(['message' => 'Foo Bar']);

        static::assertSame('context_add-customer-message', $command::getDefaultKeyName());
        static::assertSame('Foo Bar', $command->message);
    }
}
