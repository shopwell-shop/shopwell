<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnTypes;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnPresenter;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnRoute;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnRouteExtension;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One customer must never see or change the return of another, so the route is tested as the guard it
 * is: an id that is not owned by the customer answers exactly like an id that does not exist.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnRoute::class)]
class OrderReturnRouteTest extends TestCase
{
    private const SALES_CHANNEL_ID = 'sales-channel-id';

    private ?Criteria $capturedCriteria = null;

    public function testTheListOnlyAsksForTheReturnsOfTheCustomerInThisChannel(): void
    {
        $response = $this->route(orderReturnRepository: $this->repositoryCapturingCriteria())
            ->load(new Request(query: ['limit' => '10']), $this->context(), $this->customer());

        static::assertSame([], $response->getObject()->get('orderReturns'));

        $criteria = $this->capturedCriteria;
        static::assertInstanceOf(Criteria::class, $criteria);

        $filters = $criteria->getFilters();
        static::assertContainsEquals(new EqualsFilter('customerId', 'customer-id'), $filters);
        static::assertContainsEquals(new EqualsFilter('salesChannelId', self::SALES_CHANNEL_ID), $filters);
        static::assertSame(10, $criteria->getLimit());
    }

    public function testTheListIsCappedSoOneCustomerCanNotAskForEverything(): void
    {
        $this->route(orderReturnRepository: $this->repositoryCapturingCriteria())
            ->load(new Request(query: ['limit' => '500']), $this->context(), $this->customer());

        static::assertInstanceOf(Criteria::class, $this->capturedCriteria);
        // a customer asking for more than the page size gets the page size, not everything
        static::assertSame(50, $this->capturedCriteria->getLimit());
    }

    public function testAReturnOfSomebodyElseAnswersLikeAMissingOne(): void
    {
        $orderReturn = $this->orderReturn('somebody-else');

        $service = static::createStub(OrderReturnService::class);
        $service->method('get')->willReturn($orderReturn);

        $this->expectExceptionObject(OrderReturnException::notFound('return-id'));

        $this->route(service: $service)->detail(new Request(), 'return-id', $this->context(), $this->customer());
    }

    public function testTheDetailOfAnOwnReturnIsPresented(): void
    {
        $orderReturn = $this->orderReturn();

        $service = static::createStub(OrderReturnService::class);
        $service->method('get')->willReturn($orderReturn);

        $response = $this->route(service: $service)->detail(new Request(), 'return-id', $this->context(), $this->customer());

        static::assertSame('RT-1', $response->getObject()->get('orderReturn')['returnNumber']);
    }

    public function testThePositionsOfAnOrderOfSomebodyElseAreNotOffered(): void
    {
        $this->expectExceptionObject(OrderReturnException::orderNotFound('order-id'));

        $this->route(orderRepository: $this->orderRepository($this->order('another-customer-id')))
            ->positions(new Request(), 'order-id', $this->context(), $this->customer());
    }

    public function testThePositionsOfAnOwnOrderComeWithTheReasonsToPickFrom(): void
    {
        $service = static::createStub(OrderReturnService::class);
        $service->method('returnablePositions')->willReturn([['lineItemId' => 'line-item-id', 'quantity' => 2]]);
        $service->method('activeReasons')->willReturn([['id' => 'reason-id', 'label' => 'broken']]);

        $response = $this->route($service, orderRepository: $this->orderRepository($this->order('customer-id')))
            ->positions(new Request(), 'order-id', $this->context(), $this->customer());

        static::assertSame('SW-10001', $response->getObject()->get('orderNumber'));
        static::assertSame([['lineItemId' => 'line-item-id', 'quantity' => 2]], $response->getObject()->get('positions'));
        static::assertSame([['id' => 'reason-id', 'label' => 'broken']], $response->getObject()->get('reasons'));
    }

    public function testAReturnIsCreatedFromTheBodyOfTheRequest(): void
    {
        $service = $this->createMock(OrderReturnService::class);
        $service->expects($this->once())
            ->method('create')
            ->with(
                [
                    'orderId' => 'order-id',
                    'type' => OrderReturnTypes::RETURN_REFUND,
                    'positions' => ['line-item-id' => 2],
                    'reasonId' => 'reason-id',
                    'reasonText' => null,
                    'customerNote' => 'the box was empty',
                    'evidence' => ['https://example.com/photo.jpg'],
                ],
                static::isInstanceOf(Context::class),
                static::isInstanceOf(CustomerEntity::class)
            )
            ->willReturn($this->orderReturn());

        $request = new Request(request: [
            'orderId' => 'order-id',
            'type' => OrderReturnTypes::RETURN_REFUND,
            'positions' => [['lineItemId' => 'line-item-id', 'quantity' => 2], ['quantity' => 1]],
            'reasonId' => 'reason-id',
            'reasonText' => '   ',
            'customerNote' => ' the box was empty ',
            'evidence' => ['https://example.com/photo.jpg', 42],
        ]);

        $response = $this->route($service)->create($request, $this->context(), $this->customer());

        static::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        static::assertSame('RT-1', $response->getObject()->get('orderReturn')['returnNumber']);
    }

    public function testACancelOfAnOwnReturnIsHandedToTheService(): void
    {
        $orderReturn = $this->orderReturn();

        $service = $this->createMock(OrderReturnService::class);
        $service->method('get')->willReturn($orderReturn);
        $service->expects($this->once())->method('cancel')->with('return-id')->willReturn($orderReturn);

        $this->route(service: $service)->cancel(new Request(), 'return-id', $this->context(), $this->customer());
    }

    public function testAShipmentWithoutATrackingCodeIsRejected(): void
    {
        $orderReturn = $this->orderReturn();

        $service = static::createStub(OrderReturnService::class);
        $service->method('get')->willReturn($orderReturn);

        $this->expectExceptionObject(OrderReturnException::missingTrackingCode());

        $this->route(service: $service)->ship(new Request(), 'return-id', $this->context(), $this->customer());
    }

    public function testAShipmentIsAnnouncedWithTheTrackingCode(): void
    {
        $orderReturn = $this->orderReturn();

        $service = $this->createMock(OrderReturnService::class);
        $service->method('get')->willReturn($orderReturn);
        $service->expects($this->once())
            ->method('ship')
            ->with('return-id', 'SF Express', 'SF123')
            ->willReturn($orderReturn);

        $this->route(service: $service)->ship(
            new Request(request: ['trackingCode' => ' SF123 ', 'trackingCarrier' => 'SF Express']),
            'return-id',
            $this->context(),
            $this->customer()
        );
    }

    public function testAnEmptyMessageIsRejected(): void
    {
        $orderReturn = $this->orderReturn();

        $service = static::createStub(OrderReturnService::class);
        $service->method('get')->willReturn($orderReturn);

        $this->expectExceptionObject(OrderReturnException::emptyMessage());

        $this->route(service: $service)->message(
            new Request(request: ['content' => '   ']),
            'return-id',
            $this->context(),
            $this->customer()
        );
    }

    public function testAMessageOfACustomerIsWrittenWithTheCustomerAsAuthor(): void
    {
        $orderReturn = $this->orderReturn();

        $service = $this->createMock(OrderReturnService::class);
        $service->method('get')->willReturn($orderReturn);
        $service->expects($this->once())
            ->method('addMessage')
            ->with('return-id', 'any news?', OrderReturnEntity::AUTHOR_CUSTOMER, 'customer-id', null);

        $this->route(service: $service)->message(
            new Request(request: ['content' => 'any news?']),
            'return-id',
            $this->context(),
            $this->customer()
        );
    }

    public function testEveryEndpointPublishesItsExtensionFirst(): void
    {
        $orderReturn = $this->orderReturn();

        $service = static::createStub(OrderReturnService::class);
        $service->method('get')->willReturn($orderReturn);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with(static::isInstanceOf(OrderReturnRouteExtension::class));

        $this->route($service, extensions: new ExtensionDispatcher($eventDispatcher))
            ->detail(new Request(), 'return-id', $this->context(), $this->customer());
    }

    /**
     * @param StaticEntityRepository<OrderReturnCollection>|null $orderReturnRepository
     * @param StaticEntityRepository<OrderCollection>|null $orderRepository
     */
    private function route(
        ?OrderReturnService $service = null,
        ?StaticEntityRepository $orderReturnRepository = null,
        ?StaticEntityRepository $orderRepository = null,
        ?ExtensionDispatcher $extensions = null,
    ): OrderReturnRoute {
        return new OrderReturnRoute(
            $service ?? static::createStub(OrderReturnService::class),
            $orderReturnRepository ?? new StaticEntityRepository([new OrderReturnCollection()]),
            $orderRepository ?? new StaticEntityRepository([new OrderCollection()]),
            new OrderReturnPresenter(),
            $extensions ?? new ExtensionDispatcher(static::createStub(EventDispatcherInterface::class)),
        );
    }

    /**
     * @return StaticEntityRepository<OrderReturnCollection>
     */
    private function repositoryCapturingCriteria(): StaticEntityRepository
    {
        $this->capturedCriteria = null;

        return new StaticEntityRepository([
            function (Criteria $criteria): OrderReturnCollection {
                $this->capturedCriteria = $criteria;

                return new OrderReturnCollection();
            },
        ]);
    }

    /**
     * @return StaticEntityRepository<OrderCollection>
     */
    private function orderRepository(OrderEntity $order): StaticEntityRepository
    {
        return new StaticEntityRepository([new OrderCollection([$order])]);
    }

    private function order(string $customerId): OrderEntity
    {
        $order = (new OrderEntity())->assign([
            'orderNumber' => 'SW-10001',
            'orderCustomer' => (new OrderCustomerEntity())->assign([
                'id' => 'order-customer-id',
                'customerId' => $customerId,
            ]),
        ]);
        $order->setId('order-id');
        $order->setUniqueIdentifier('order-id');

        return $order;
    }

    private function orderReturn(string $customerId = 'customer-id'): OrderReturnEntity
    {
        $orderReturn = (new OrderReturnEntity())->assign([
            'id' => 'return-id',
            'returnNumber' => 'RT-1',
            'orderId' => 'order-id',
            'customerId' => $customerId,
            'type' => OrderReturnTypes::RETURN_REFUND,
            'refundAmount' => 1999,
            'currency' => 'CNY',
        ]);
        $orderReturn->setUniqueIdentifier($orderReturn->id);

        return $orderReturn;
    }

    private function customer(): CustomerEntity
    {
        return (new CustomerEntity())->assign(['id' => 'customer-id']);
    }

    private function context(): SalesChannelContext
    {
        $salesChannel = (new SalesChannelEntity())->assign(['id' => self::SALES_CHANNEL_ID]);

        return Generator::generateSalesChannelContext(salesChannel: $salesChannel);
    }
}
