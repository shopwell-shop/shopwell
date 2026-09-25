<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Gateway\Context\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopwell\Core\Framework\Gateway\Context\Command\Handler\LoginCustomerCommandHandler;
use Shopwell\Core\Framework\Gateway\Context\Command\LoginCustomerCommand;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LoginCustomerCommandHandler::class)]
class LoginCustomerCommandHandlerTest extends TestCase
{
    public function testHandle(): void
    {
        $command = LoginCustomerCommand::createFromPayload(['customerEmail' => 'foo@bar.com']);
        $context = Generator::generateSalesChannelContext();
        $parameters = [];

        $customer = new CustomerEntity();
        $customer->setId('customerId');

        $accountService = $this->createMock(AccountService::class);
        $accountService
            ->expects($this->once())
            ->method('getCustomerByEmail')
            ->with('foo@bar.com', $context)
            ->willReturn($customer);

        $accountService
            ->expects($this->once())
            ->method('loginById')
            ->with('customerId', $context)
            ->willReturn('newHatoken');

        $handler = new LoginCustomerCommandHandler($accountService);
        $handler->handle($command, $context, $parameters);

        static::assertSame(['token' => 'newHatoken'], $parameters);
    }

    public function testSupportedCommands(): void
    {
        static::assertSame([LoginCustomerCommand::class], LoginCustomerCommandHandler::supportedCommands());
    }
}
