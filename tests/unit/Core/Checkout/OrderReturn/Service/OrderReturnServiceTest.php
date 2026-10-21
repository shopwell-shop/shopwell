<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Order\OrderStates;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnStates;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnTypes;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnNumberGenerator;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnQuantityGuard;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnRefundService;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnService;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnSettings;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnStateHandler;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnStockRestorer;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyEntity;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The service is the only place that knows which state may do what and what a position is worth: the
 * amount is calculated from the order and never taken from a request, so a customer can not name a
 * price.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnService::class)]
class OrderReturnServiceTest extends TestCase
{
    private Context $context;

    private OrderReturnQuantityGuard $quantityGuard;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->quantityGuard = static::createStub(OrderReturnQuantityGuard::class);
    }

    public function testTheAmountIsCalculatedFromTheOrderedPriceAndSummed(): void
    {
        $cheap = $this->productLineItem('cheap', 10.5);
        $expensive = $this->productLineItem('expensive', 59.5);
        $repository = $this->returnRepository();

        $service = $this->service($this->order([$cheap, $expensive]), repository: $repository);

        $service->create([
            'orderId' => 'order-id',
            'type' => OrderReturnTypes::RETURN_REFUND,
            'positions' => ['cheap' => 2, 'expensive' => 1],
        ], $this->context, $this->customer());

        $payload = $repository->creates[0][0];

        // 2 x 10.50 + 1 x 59.50, both in the smallest unit of the currency
        static::assertSame(2100 + 5950, $payload['refundAmount']);
        static::assertCount(2, $payload['lineItems']);
        static::assertSame(
            [['id', 'orderLineItemId', 'orderLineItemVersionId', 'quantity', 'refundAmount', 'restocked', 'accepted']],
            [array_keys($payload['lineItems'][0])]
        );
        static::assertFalse($payload['lineItems'][0]['restocked']);
        static::assertTrue($payload['lineItems'][0]['accepted']);
    }

    public function testAReturnOfSomeoneElseIsNotToldApartFromAMissingOne(): void
    {
        $order = $this->order([$this->productLineItem('line-item', 10.0)]);
        $stranger = (new CustomerEntity())->assign(['id' => 'another-customer-id']);

        $this->expectExceptionObject(OrderReturnException::orderNotFound('SW-10001'));

        $this->service($order)->create([
            'orderId' => 'order-id',
            'type' => OrderReturnTypes::RETURN_REFUND,
            'positions' => ['line-item' => 1],
        ], $this->context, $stranger);
    }

    public function testAGuestProvesTheOrderByTheMailAddressOfTheOrder(): void
    {
        $repository = $this->returnRepository();

        $this->service($this->order([$this->productLineItem('line-item', 10.0)]), repository: $repository)->create([
            'orderId' => 'order-id',
            'type' => OrderReturnTypes::REFUND_ONLY,
            'positions' => ['line-item' => 1],
            'guestEmail' => ' Customer@Example.com ',
            'guestTokenHash' => 'the-hash',
        ], $this->context, null);

        $payload = $repository->creates[0][0];

        static::assertSame('customer@example.com', $payload['guestEmail']);
        static::assertSame('the-hash', $payload['guestTokenHash']);
        static::assertNull($payload['customerId']);
    }

    public function testAGuestReturnNeedsAnAddress(): void
    {
        $this->expectExceptionObject(OrderReturnException::orderNotFound('SW-10001'));

        $this->service($this->order([$this->productLineItem('line-item', 10.0)]))->create([
            'orderId' => 'order-id',
            'type' => OrderReturnTypes::REFUND_ONLY,
            'positions' => ['line-item' => 1],
        ], $this->context, null);
    }

    public function testAnUnknownTypeIsRejected(): void
    {
        $this->expectExceptionObject(OrderReturnException::invalidType('repair'));

        $this->service($this->order([]))->create([
            'orderId' => 'order-id',
            'type' => 'repair',
            'positions' => ['line-item' => 1],
        ], $this->context, $this->customer());
    }

    public function testAChannelWithoutReturnsIsRejected(): void
    {
        $this->expectExceptionObject(OrderReturnException::disabled());

        $this->service($this->order([$this->productLineItem('line-item', 10.0)]), $this->settings(enabled: false))->create([
            'orderId' => 'order-id',
            'type' => OrderReturnTypes::REFUND_ONLY,
            'positions' => ['line-item' => 1],
        ], $this->context, $this->customer());
    }

    public function testACanceledOrderIsNotReturned(): void
    {
        $order = $this->order([$this->productLineItem('line-item', 10.0)], OrderStates::STATE_CANCELLED);

        $this->expectExceptionObject(OrderReturnException::orderNotFinished('SW-10001'));

        $this->service($order)->create([
            'orderId' => 'order-id',
            'type' => OrderReturnTypes::REFUND_ONLY,
            'positions' => ['line-item' => 1],
        ], $this->context, $this->customer());
    }

    public function testAReturnWithoutPositionsIsRejected(): void
    {
        $this->expectExceptionObject(OrderReturnException::emptyPositions());

        $this->service($this->order([$this->productLineItem('line-item', 10.0)]))->create([
            'orderId' => 'order-id',
            'type' => OrderReturnTypes::REFUND_ONLY,
            'positions' => [],
        ], $this->context, $this->customer());
    }

    public function testAWindowThatCountsFromTheOrderDateCanBeOver(): void
    {
        $order = $this->order([$this->productLineItem('line-item', 10.0)]);
        $order->setOrderDate(new \DateTimeImmutable('-40 days'));
        $settings = $this->settings();

        $this->expectExceptionObject(OrderReturnException::windowExpired('SW-10001', 30));

        $this->service($order, $settings)->create([
            'orderId' => 'order-id',
            'type' => OrderReturnTypes::REFUND_ONLY,
            'positions' => ['line-item' => 1],
        ], $this->context, $this->customer());
    }

    public function testAWindowOfZeroDaysNeverCloses(): void
    {
        $order = $this->order([$this->productLineItem('line-item', 10.0)]);
        $order->setOrderDate(new \DateTimeImmutable('-400 days'));
        $settings = $this->settings(windowDays: 0);

        $this->service($order, $settings)->create([
            'orderId' => 'order-id',
            'type' => OrderReturnTypes::REFUND_ONLY,
            'positions' => ['line-item' => 1],
        ], $this->context, $this->customer());

        static::addToAssertionCount(1);
    }

    public function testTheDeadlineOfAnApprovalFollowsTheSetting(): void
    {
        $repository = $this->returnRepository();

        $this->service($this->order([$this->productLineItem('line-item', 10.0)]), repository: $repository)->create([
            'orderId' => 'order-id',
            'type' => OrderReturnTypes::REFUND_ONLY,
            'positions' => ['line-item' => 1],
        ], $this->context, $this->customer());

        /** @var \DateTimeInterface $deadline */
        $deadline = $repository->creates[0][0]['deadlineAt'];

        static::assertSame('2026-10-23 09:30:15', $deadline->format('Y-m-d H:i:s'));
    }

    public function testAnActionThatTheStateDoesNotAllowIsRejected(): void
    {
        $orderReturn = $this->orderReturn(OrderReturnStates::STATE_CLOSED);

        $this->expectExceptionObject(OrderReturnException::stateNotAllowed(OrderReturnStates::STATE_CLOSED, OrderReturnStates::ACTION_APPROVE));

        $this->service($this->order([]), currentReturn: $orderReturn)->approve($orderReturn->id, $this->context);
    }

    /**
     * @param StaticEntityRepository<OrderReturnCollection>|null $repository
     */
    private function service(
        OrderEntity $order,
        ?OrderReturnSettings $settings = null,
        ?StaticEntityRepository $repository = null,
        ?OrderReturnEntity $currentReturn = null,
    ): OrderReturnService {
        $numberGenerator = static::createStub(OrderReturnNumberGenerator::class);
        $numberGenerator->method('generate')->willReturn('RT-1');

        $initialStateIds = static::createStub(InitialStateIdLoader::class);
        $initialStateIds->method('get')->willReturn('initial-state-id');

        return new OrderReturnService(
            $repository ?? $this->returnRepository($currentReturn),
            new StaticEntityRepository([]),
            new StaticEntityRepository([]),
            new StaticEntityRepository([new OrderCollection([$order])]),
            $settings ?? $this->settings(),
            $numberGenerator,
            static::createStub(OrderReturnStateHandler::class),
            $this->quantityGuard,
            static::createStub(OrderReturnStockRestorer::class),
            static::createStub(OrderReturnRefundService::class),
            $initialStateIds,
            static::createStub(EventDispatcherInterface::class),
            new MockClock('2026-10-21 09:30:15'),
        );
    }

    /**
     * @return StaticEntityRepository<OrderReturnCollection>
     */
    private function returnRepository(?OrderReturnEntity $currentReturn = null): StaticEntityRepository
    {
        $orderReturn = $currentReturn ?? (new OrderReturnEntity())->assign([
            'id' => 'return-id',
            'returnNumber' => 'RT-1',
            'type' => OrderReturnTypes::RETURN_REFUND,
            'refundAmount' => 0,
            'currency' => 'CNY',
        ]);
        $orderReturn->setUniqueIdentifier($orderReturn->id);

        return new StaticEntityRepository([new OrderReturnCollection([$orderReturn])]);
    }

    private function settings(bool $enabled = true, int $windowDays = 30): OrderReturnSettings
    {
        $settings = $this->createMock(OrderReturnSettings::class);
        $settings->method('isEnabled')->willReturn($enabled);
        $settings->method('allowGuest')->willReturn(true);
        $settings->method('windowDays')->willReturn($windowDays);
        $settings->method('windowStartsAtDelivery')->willReturn(false);
        $settings->method('approvalDeadlineHours')->willReturn(48);
        $settings->method('returnDeadlineDays')->willReturn(14);
        $settings->method('restockOnReceive')->willReturn(true);

        return $settings;
    }

    private function customer(): CustomerEntity
    {
        return (new CustomerEntity())->assign(['id' => 'customer-id']);
    }

    /**
     * @param list<OrderLineItemEntity> $lineItems
     */
    private function order(array $lineItems, string $state = OrderStates::STATE_OPEN): OrderEntity
    {
        foreach ($lineItems as $lineItem) {
            $lineItem->setUniqueIdentifier($lineItem->getId());
        }

        $currency = (new CurrencyEntity())->assign(['id' => 'currency-id', 'isoCode' => 'CNY']);
        $currency->setItemRounding(new CashRoundingConfig(2, 0.01, true));

        return (new OrderEntity())->assign([
            'id' => 'order-id',
            'orderNumber' => 'SW-10001',
            'orderDate' => new \DateTimeImmutable('2026-10-01 10:00:00'),
            'salesChannelId' => 'sales-channel-id',
            'versionId' => 'order-version-id',
            'currency' => $currency,
            'currencyId' => 'currency-id',
            'stateMachineState' => (new StateMachineStateEntity())->assign(['technicalName' => $state]),
            'orderCustomer' => (new OrderCustomerEntity())->assign([
                'id' => 'order-customer-id',
                'customerId' => 'customer-id',
                'email' => 'customer@example.com',
            ]),
            'lineItems' => new OrderLineItemCollection($lineItems),
        ]);
    }

    private function productLineItem(string $id, float $unitPrice): OrderLineItemEntity
    {
        return (new OrderLineItemEntity())->assign([
            'id' => $id,
            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'quantity' => 5,
            'label' => 'a product',
            'unitPrice' => $unitPrice,
            'versionId' => 'order-version-id',
        ]);
    }

    private function orderReturn(string $state): OrderReturnEntity
    {
        $orderReturn = (new OrderReturnEntity())->assign([
            'id' => 'return-id',
            'returnNumber' => 'RT-1',
            'orderId' => 'order-id',
            'type' => OrderReturnTypes::RETURN_REFUND,
            'refundAmount' => 0,
            'currency' => 'CNY',
            'salesChannelId' => 'sales-channel-id',
            'stateMachineState' => (new StateMachineStateEntity())->assign(['technicalName' => $state]),
        ]);
        $orderReturn->setUniqueIdentifier($orderReturn->id);

        return $orderReturn;
    }
}
