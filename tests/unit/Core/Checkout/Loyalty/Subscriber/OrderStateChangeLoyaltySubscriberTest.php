<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Loyalty\Cart\LoyaltyPointCartProcessor;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointService;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Checkout\Loyalty\Service\TierResolver;
use Shopwell\Core\Checkout\Loyalty\Subscriber\OrderStateChangeLoyaltySubscriber;
use Shopwell\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Order\OrderStates;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderStateChangeLoyaltySubscriber::class)]
class OrderStateChangeLoyaltySubscriberTest extends TestCase
{
    public function testIgnoresNonLiveVersion(): void
    {
        $context = Context::createDefaultContext()->createWithVersionId(Uuid::randomHex());

        $loyaltyPointService = static::createMock(LoyaltyPointService::class);
        $loyaltyPointService->expects($this->never())->method('award');
        $loyaltyPointService->expects($this->never())->method('redeem');

        $this->createSubscriber($loyaltyPointService, [$this->createOrder('customer-id', 100.0, [])])
            ->onOrderStateChange($this->createEvent('order', Uuid::randomHex(), OrderStates::STATE_COMPLETED, $context));
    }

    public function testIgnoresEntityOtherThanOrder(): void
    {
        $loyaltyPointService = static::createMock(LoyaltyPointService::class);
        $loyaltyPointService->expects($this->never())->method('award');
        $loyaltyPointService->expects($this->never())->method('redeem');

        $this->createSubscriber($loyaltyPointService, [$this->createOrder('customer-id', 100.0, [])])
            ->onOrderStateChange($this->createEvent('customer', Uuid::randomHex(), OrderStates::STATE_COMPLETED));
    }

    public function testIgnoresStateOtherThanCompleted(): void
    {
        $loyaltyPointService = static::createMock(LoyaltyPointService::class);
        $loyaltyPointService->expects($this->never())->method('award');
        $loyaltyPointService->expects($this->never())->method('redeem');

        $this->createSubscriber($loyaltyPointService, [$this->createOrder('customer-id', 100.0, [])])
            ->onOrderStateChange($this->createEvent('order', Uuid::randomHex(), OrderStates::STATE_OPEN));
    }

    public function testIgnoresUnknownOrder(): void
    {
        $loyaltyPointService = static::createMock(LoyaltyPointService::class);
        $loyaltyPointService->expects($this->never())->method('award');
        $loyaltyPointService->expects($this->never())->method('redeem');

        $this->createSubscriber($loyaltyPointService, [])
            ->onOrderStateChange($this->createEvent('order', Uuid::randomHex(), OrderStates::STATE_COMPLETED));
    }

    public function testIgnoresOrderWithoutCustomer(): void
    {
        $loyaltyPointService = static::createMock(LoyaltyPointService::class);
        $loyaltyPointService->expects($this->never())->method('award');
        $loyaltyPointService->expects($this->never())->method('redeem');

        $this->createSubscriber($loyaltyPointService, [$this->createOrder(null, 100.0, [])])
            ->onOrderStateChange($this->createEvent('order', Uuid::randomHex(), OrderStates::STATE_COMPLETED));
    }

    public function testRedeemsPointsRedeemedWhilePlacingTheOrder(): void
    {
        $customerId = Uuid::randomHex();
        $orderId = Uuid::randomHex();

        $lineItem = $this->createCreditLineItem(LoyaltyPointCartProcessor::LINE_ITEM_ID, ['points' => 50]);

        $loyaltyPointService = static::createMock(LoyaltyPointService::class);
        $loyaltyPointService->method('getBalance')->willReturn(100);
        $loyaltyPointService->expects($this->once())->method('redeem')
            ->with($customerId, 50, $orderId, $orderId . '-redeemed', static::anything());

        $this->createSubscriber(
            $loyaltyPointService,
            [$this->createOrder($customerId, 0.0, [$lineItem], $orderId)],
        )->onOrderStateChange($this->createEvent('order', $orderId, OrderStates::STATE_COMPLETED));
    }

    public function testCapsRedemptionToAvailableBalance(): void
    {
        $customerId = Uuid::randomHex();
        $orderId = Uuid::randomHex();

        $lineItem = $this->createCreditLineItem(LoyaltyPointCartProcessor::LINE_ITEM_ID, ['points' => 150]);

        $loyaltyPointService = static::createMock(LoyaltyPointService::class);
        $loyaltyPointService->method('getBalance')->willReturn(80);
        $loyaltyPointService->expects($this->once())->method('redeem')
            ->with($customerId, 80, $orderId, $orderId . '-redeemed', static::anything());

        $this->createSubscriber(
            $loyaltyPointService,
            [$this->createOrder($customerId, 0.0, [$lineItem], $orderId)],
        )->onOrderStateChange($this->createEvent('order', $orderId, OrderStates::STATE_COMPLETED));
    }

    public function testDoesNotRedeemWithoutRedemptionPayload(): void
    {
        $loyaltyPointService = static::createMock(LoyaltyPointService::class);
        $loyaltyPointService->expects($this->never())->method('getBalance');
        $loyaltyPointService->expects($this->never())->method('redeem');

        $this->createSubscriber(
            $loyaltyPointService,
            [$this->createOrder(Uuid::randomHex(), 0.0, [])],
        )->onOrderStateChange($this->createEvent('order', Uuid::randomHex(), OrderStates::STATE_COMPLETED));
    }

    public function testAwardsPointsAndGrowthAndResolvesTier(): void
    {
        $customerId = Uuid::randomHex();
        $orderId = Uuid::randomHex();

        $loyaltyPointService = static::createMock(LoyaltyPointService::class);
        $loyaltyPointService->expects($this->once())->method('award')
            ->with($customerId, 300, $orderId, $orderId . '-earned', static::anything());
        $loyaltyPointService->expects($this->once())->method('addGrowthValue')
            ->with($customerId, 100, static::anything())
            ->willReturn(120);

        $tierResolver = static::createMock(TierResolver::class);
        $tierResolver->method('getPointsMultiplier')->willReturn(1.5);
        $tierResolver->expects($this->once())->method('resolve')
            ->with($customerId, 120, static::anything());

        $subscriber = new OrderStateChangeLoyaltySubscriber(
            StaticEntityRepository::of(OrderCollection::class, [[$this->createOrder($customerId, 100.0, [], $orderId)]]),
            $loyaltyPointService,
            $tierResolver,
            new LoyaltySettings(new StaticSystemConfigService([
                LoyaltySettings::CONFIG_KEY_POINTS_PER_CURRENCY_UNIT => 2,
                LoyaltySettings::CONFIG_KEY_GROWTH_PER_CURRENCY_UNIT => 1,
            ])),
        );

        $subscriber->onOrderStateChange($this->createEvent('order', $orderId, OrderStates::STATE_COMPLETED));
    }

    public function testDoesNotAwardWhenRatesAreZero(): void
    {
        $loyaltyPointService = static::createMock(LoyaltyPointService::class);
        $loyaltyPointService->expects($this->never())->method('award');
        $loyaltyPointService->expects($this->never())->method('redeem');

        $this->createSubscriber(
            $loyaltyPointService,
            [$this->createOrder(Uuid::randomHex(), 100.0, [])],
            [
                LoyaltySettings::CONFIG_KEY_POINTS_PER_CURRENCY_UNIT => 0,
                LoyaltySettings::CONFIG_KEY_GROWTH_PER_CURRENCY_UNIT => 0,
            ],
        )->onOrderStateChange($this->createEvent('order', Uuid::randomHex(), OrderStates::STATE_COMPLETED));
    }

    public function testDoesNotAwardWhenTheMembershipIsDisabled(): void
    {
        $customerId = Uuid::randomHex();

        $loyaltyPointService = static::createMock(LoyaltyPointService::class);
        $loyaltyPointService->expects($this->never())->method('award');
        $loyaltyPointService->expects($this->never())->method('addGrowthValue');

        $this->createSubscriber(
            $loyaltyPointService,
            [$this->createOrder($customerId, 100.0, [])],
            [LoyaltySettings::CONFIG_KEY_ENABLED => false],
        )->onOrderStateChange($this->createEvent('order', $customerId, OrderStates::STATE_COMPLETED));
    }

    public function testResolvesTheTierWithTheGrowthValueTheLedgerAnswered(): void
    {
        $customerId = Uuid::randomHex();

        $loyaltyPointService = static::createStub(LoyaltyPointService::class);
        $loyaltyPointService->method('addGrowthValue')->willReturn(40);

        $tierResolver = static::createMock(TierResolver::class);
        $tierResolver->method('getPointsMultiplier')->willReturn(1.0);
        $tierResolver->expects($this->once())->method('resolve')
            ->with($customerId, 40, static::anything());

        $subscriber = new OrderStateChangeLoyaltySubscriber(
            StaticEntityRepository::of(OrderCollection::class, [[$this->createOrder($customerId, 100.0, [])]]),
            $loyaltyPointService,
            $tierResolver,
            new LoyaltySettings(new StaticSystemConfigService([
                LoyaltySettings::CONFIG_KEY_GROWTH_PER_CURRENCY_UNIT => 1,
            ])),
        );

        $subscriber->onOrderStateChange($this->createEvent('order', $customerId, OrderStates::STATE_COMPLETED));
    }

    /**
     * @param list<OrderEntity> $orders
     * @param array<string, mixed> $config
     */
    private function createSubscriber(LoyaltyPointService $loyaltyPointService, array $orders, array $config = []): OrderStateChangeLoyaltySubscriber
    {
        return new OrderStateChangeLoyaltySubscriber(
            StaticEntityRepository::of(OrderCollection::class, $orders === [] ? [[]] : [$orders]),
            $loyaltyPointService,
            static::createStub(TierResolver::class),
            new LoyaltySettings(new StaticSystemConfigService($config)),
        );
    }

    private function createEvent(
        string $entityName,
        string $entityId,
        string $toState,
        ?Context $context = null
    ): StateMachineTransitionEvent {
        $fromState = new StateMachineStateEntity();
        $fromState->setTechnicalName(OrderStates::STATE_OPEN);

        $toPlace = new StateMachineStateEntity();
        $toPlace->setTechnicalName($toState);

        return new StateMachineTransitionEvent(
            $entityName,
            $entityId,
            $fromState,
            $toPlace,
            $context ?? Context::createDefaultContext()
        );
    }

    /**
     * @param list<OrderLineItemEntity> $lineItems
     */
    private function createOrder(?string $customerId, float $amountTotal, array $lineItems, ?string $orderId = null): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId($orderId ?? Uuid::randomHex());
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setAmountTotal($amountTotal);

        if ($customerId !== null) {
            $orderCustomer = new OrderCustomerEntity();
            $orderCustomer->setCustomerId($customerId);
            $order->setOrderCustomer($orderCustomer);
        }

        $order->setLineItems(new OrderLineItemCollection($lineItems));

        return $order;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createCreditLineItem(string $identifier, array $payload): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId(Uuid::randomHex());
        $lineItem->setIdentifier($identifier);
        $lineItem->setType(LineItem::CREDIT_LINE_ITEM_TYPE);
        $lineItem->setPayload($payload);

        return $lineItem;
    }
}
