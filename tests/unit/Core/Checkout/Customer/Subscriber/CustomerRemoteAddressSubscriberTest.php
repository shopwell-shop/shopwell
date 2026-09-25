<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopwell\Core\Checkout\Customer\Subscriber\CustomerRemoteAddressSubscriber;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerRemoteAddressSubscriber::class)]
class CustomerRemoteAddressSubscriberTest extends TestCase
{
    public function testEvents(): void
    {
        static::assertSame([
            CustomerLoginEvent::class => 'updateRemoteAddressByLogin',
        ], CustomerRemoteAddressSubscriber::getSubscribedEvents());
    }

    public function testNoRequestThereHappensNothing(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->never())->method('getBool');

        $subscriber = new CustomerRemoteAddressSubscriber(
            static::createStub(Connection::class),
            new RequestStack(),
            $configService
        );

        $subscriber->updateRemoteAddressByLogin(new CustomerLoginEvent(static::createStub(SalesChannelContext::class), new CustomerEntity(), 'test'));
    }

    public function testNullIpDoesNothing(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->never())->method('getBool');

        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $subscriber = new CustomerRemoteAddressSubscriber(
            static::createStub(Connection::class),
            $requestStack,
            $configService
        );

        $subscriber->updateRemoteAddressByLogin(new CustomerLoginEvent(static::createStub(SalesChannelContext::class), new CustomerEntity(), 'test'));
    }

    public static function providerIPs(): \Generator
    {
        yield 'enabled, should anonymize' => [
            false,
            '94.31.83.28',
            '94.31.83.0',
        ];

        yield 'enabled, should not anonymize' => [
            true,
            '94.31.83.28',
            '94.31.83.28',
        ];
    }

    #[DataProvider('providerIPs')]
    public function testRequest(bool $anonymize, string $clientIp, string $expectedIp): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->once())->method('getBool')->willReturn($anonymize);

        $requestStack = new RequestStack();

        $request = new Request();
        $request->server->set('REMOTE_ADDR', $clientIp);

        $requestStack->push($request);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('update')
            ->with(
                'customer',
                ['remote_address' => $expectedIp],
                ['id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL)]
            );

        $subscriber = new CustomerRemoteAddressSubscriber(
            $connection,
            $requestStack,
            $configService
        );

        $customer = new CustomerEntity();
        $customer->setUniqueIdentifier(TestDefaults::SALES_CHANNEL);
        $customer->setId(TestDefaults::SALES_CHANNEL);

        $subscriber->updateRemoteAddressByLogin(new CustomerLoginEvent(static::createStub(SalesChannelContext::class), $customer, 'test'));
    }
}
