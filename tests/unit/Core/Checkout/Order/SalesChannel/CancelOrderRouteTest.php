<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderException;
use Shopwell\Core\Checkout\Order\SalesChannel\CancelOrderRoute;
use Shopwell\Core\Checkout\Order\SalesChannel\OrderService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CancelOrderRoute::class)]
class CancelOrderRouteTest extends TestCase
{
    public function testRefundsDisabled(): void
    {
        $this->expectExceptionObject(OrderException::orderNotCancellable());

        $route = new CancelOrderRoute(
            static::createStub(OrderService::class),
            static::createStub(EntityRepository::class),
            new StaticSystemConfigService([
                'core.cart.enableOrderRefunds' => false,
            ]),
        );

        $route->cancel(new Request(['orderId' => Uuid::randomHex()]), static::createStub(SalesChannelContext::class));
    }

    public function testNoOrderId(): void
    {
        $this->expectExceptionObject(OrderException::invalidRequestParameter('orderId'));

        $route = new CancelOrderRoute(
            static::createStub(OrderService::class),
            static::createStub(EntityRepository::class),
            new StaticSystemConfigService([
                'core.cart.enableOrderRefunds' => true,
            ]),
        );

        $route->cancel(new Request(), static::createStub(SalesChannelContext::class));
    }

    public function testNotLoggedIn(): void
    {
        $this->expectExceptionObject(OrderException::customerNotLoggedIn());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getCustomer')
            ->willReturn(null);

        $route = new CancelOrderRoute(
            static::createStub(OrderService::class),
            static::createStub(EntityRepository::class),
            new StaticSystemConfigService([
                'core.cart.enableOrderRefunds' => true,
            ]),
        );

        $route->cancel(new Request([], ['orderId' => Uuid::randomHex()]), $salesChannelContext);
    }

    public function testOrderNotFound(): void
    {
        $this->expectException(OrderException::class);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getCustomer')
            ->willReturn($customer);
        $salesChannelContext
            ->expects($this->once())
            ->method('getCustomerId')
            ->willReturn($customer->getId());

        /** @var StaticEntityRepository<OrderCollection> */
        $orderRepository = new StaticEntityRepository([[]]);

        $route = new CancelOrderRoute(
            static::createStub(OrderService::class),
            $orderRepository,
            new StaticSystemConfigService([
                'core.cart.enableOrderRefunds' => true,
            ]),
        );

        $route->cancel(new Request([], ['orderId' => Uuid::randomHex()]), $salesChannelContext);
    }

    public function testCancelOrder(): void
    {
        $orderId = Uuid::randomHex();
        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects($this->once())
            ->method('getCustomer')
            ->willReturn($customer);
        $salesChannelContext
            ->expects($this->once())
            ->method('getCustomerId')
            ->willReturn($customer->getId());
        $salesChannelContext
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $orderService = $this->createMock(OrderService::class);
        $orderService
            ->expects($this->once())
            ->method('orderStateTransition')
            ->with($orderId, 'cancel', new ParameterBag(), Context::createDefaultContext())
            ->willReturn(new StateMachineStateEntity());

        /** @var StaticEntityRepository<OrderCollection> */
        $orderRepository = new StaticEntityRepository([[Uuid::randomHex()]]);

        $route = new CancelOrderRoute(
            $orderService,
            $orderRepository,
            new StaticSystemConfigService([
                'core.cart.enableOrderRefunds' => true,
            ]),
        );

        $route->cancel(new Request([], ['orderId' => $orderId]), $salesChannelContext);
    }
}
