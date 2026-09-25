<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Gateway\Context\Command\AbstractContextGatewayCommand;
use Shopwell\Core\Framework\Gateway\Context\Command\ChangePaymentMethodCommand;
use Shopwell\Core\Framework\Gateway\Context\Command\ContextGatewayCommandCollection;
use Shopwell\Core\Framework\Gateway\Context\Command\LoginCustomerCommand;
use Shopwell\Core\Framework\Gateway\Context\Command\RegisterCustomerCommand;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextGatewayCommandCollection::class)]
class ContextGatewayCommandCollectionTest extends TestCase
{
    public function testGetTokenCommandWithRegister(): void
    {
        $commands = new ContextGatewayCommandCollection();
        $commands->add(self::getCommand(RegisterCustomerCommand::class, ['data' => ['foo' => 'bar']]));
        $commands->add(self::getCommand(RegisterCustomerCommand::class, ['data' => ['wow' => 'ser']]));

        $registerCommand = $commands->getSingleTokenCommand();

        static::assertInstanceOf(RegisterCustomerCommand::class, $registerCommand);
        static::assertSame(['foo' => 'bar'], $registerCommand->data);
    }

    public function testGetTokenCommandWithLogin(): void
    {
        $commands = new ContextGatewayCommandCollection();
        $commands->add(self::getCommand(LoginCustomerCommand::class, ['customerEmail' => 'foo@bar.com']));
        $commands->add(self::getCommand(LoginCustomerCommand::class, ['customerEmail' => 'wow@ser.com']));

        $loginCommand = $commands->getSingleTokenCommand();

        static::assertInstanceOf(LoginCustomerCommand::class, $loginCommand);
        static::assertSame('foo@bar.com', $loginCommand->customerEmail);
    }

    public function testGetTokenCommandWithNull(): void
    {
        $commands = new ContextGatewayCommandCollection();
        $commands->add(self::getCommand(ChangePaymentMethodCommand::class, ['technicalName' => 'test_payment']));

        $tokenCommand = $commands->getSingleTokenCommand();

        static::assertNull($tokenCommand);
    }

    /**
     * @template T of AbstractContextGatewayCommand
     *
     * @param class-string<T> $type
     * @param array<string, mixed> $data
     */
    private static function getCommand(string $type, array $data = []): AbstractContextGatewayCommand
    {
        return $type::createFromPayload($data);
    }
}
