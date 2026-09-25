<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Gateway\Context\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Gateway\Context\Command\AddCustomerMessageCommand;
use Shopwell\Core\Framework\Gateway\Context\Command\Handler\AddCustomerMessageCommandHandler;
use Shopwell\Core\Framework\Gateway\GatewayException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AddCustomerMessageCommandHandler::class)]
class AddCustomerMessageCommandHandlerTest extends TestCase
{
    public function testAddCustomerMessage(): void
    {
        $command = AddCustomerMessageCommand::createFromPayload(['message' => 'Foo Bar']);
        $context = Generator::generateSalesChannelContext();
        $parameters = [];

        $this->expectExceptionObject(GatewayException::customerMessage('Foo Bar'));

        $handler = new AddCustomerMessageCommandHandler();
        $handler->handle($command, $context, $parameters);
    }

    public function testGetSupportedCommands(): void
    {
        static::assertSame([AddCustomerMessageCommand::class], AddCustomerMessageCommandHandler::supportedCommands());
    }
}
