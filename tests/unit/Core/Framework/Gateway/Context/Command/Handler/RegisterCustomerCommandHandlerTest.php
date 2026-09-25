<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Gateway\Context\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\SalesChannel\AbstractRegisterRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\CustomerResponse;
use Shopwell\Core\Framework\Gateway\Context\Command\Handler\RegisterCustomerCommandHandler;
use Shopwell\Core\Framework\Gateway\Context\Command\RegisterCustomerCommand;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RegisterCustomerCommandHandler::class)]
class RegisterCustomerCommandHandlerTest extends TestCase
{
    public function testHandle(): void
    {
        $command = RegisterCustomerCommand::createFromPayload(
            [
                'data' => [
                    'billingAddress' => ['foo' => 'bar'],
                    'shippingAddress' => ['baz' => 'bat'],
                    'vatIds' => ['vatId1', 'vatId2'],
                ],
            ]
        );

        $context = Generator::generateSalesChannelContext();
        $parameters = [];

        $expectedData = new RequestDataBag(
            [
                'vatIds' => new RequestDataBag(['vatId1', 'vatId2']),
                'billingAddress' => ['foo' => 'bar'],
                'shippingAddress' => ['baz' => 'bat'],
            ]
        );

        $response = new CustomerResponse(new CustomerEntity());
        $response->headers->set('sw-context-token', 'hatoken');

        $registerRoute = $this->createMock(AbstractRegisterRoute::class);
        $registerRoute
            ->expects($this->once())
            ->method('register')
            ->with($expectedData, $context)
            ->willReturn($response);

        $handler = new RegisterCustomerCommandHandler($registerRoute);
        $handler->handle($command, $context, $parameters);

        static::assertSame(['token' => 'hatoken'], $parameters);
    }

    public function testSupportedCommands(): void
    {
        static::assertSame([RegisterCustomerCommand::class], RegisterCustomerCommandHandler::supportedCommands());
    }
}
