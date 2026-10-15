<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Order\OrderStates;
use Shopwell\Core\Checkout\Wallet\Cart\WalletCartProcessor;
use Shopwell\Core\Checkout\Wallet\Service\WalletService;
use Shopwell\Core\Checkout\Wallet\Subscriber\WalletOrderSubscriber;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletOrderSubscriber::class)]
class WalletOrderSubscriberTest extends TestCase
{
    public function testIgnoresNonLiveVersion(): void
    {
        $context = Context::createDefaultContext()->createWithVersionId(Uuid::randomHex());

        $walletService = static::createMock(WalletService::class);
        $walletService->expects($this->never())->method('consume');

        $this->createSubscriber($walletService, [$this->createOrder('customer-id', Uuid::randomHex(), [])])
            ->onOrderStateChange($this->createEvent('order', Uuid::randomHex(), OrderStates::STATE_COMPLETED, $context));
    }

    public function testIgnoresEntityOtherThanOrder(): void
    {
        $walletService = static::createMock(WalletService::class);
        $walletService->expects($this->never())->method('consume');

        $this->createSubscriber($walletService, [$this->createOrder('customer-id', Uuid::randomHex(), [])])
            ->onOrderStateChange($this->createEvent('customer', Uuid::randomHex(), OrderStates::STATE_COMPLETED));
    }

    public function testIgnoresStateOtherThanCompleted(): void
    {
        $walletService = static::createMock(WalletService::class);
        $walletService->expects($this->never())->method('consume');

        $this->createSubscriber($walletService, [$this->createOrder('customer-id', Uuid::randomHex(), [])])
            ->onOrderStateChange($this->createEvent('order', Uuid::randomHex(), OrderStates::STATE_OPEN));
    }

    public function testIgnoresUnknownOrder(): void
    {
        $walletService = static::createMock(WalletService::class);
        $walletService->expects($this->never())->method('consume');

        $this->createSubscriber($walletService, [])
            ->onOrderStateChange($this->createEvent('order', Uuid::randomHex(), OrderStates::STATE_COMPLETED));
    }

    public function testIgnoresOrderWithoutCustomer(): void
    {
        $walletService = static::createMock(WalletService::class);
        $walletService->expects($this->never())->method('consume');

        $this->createSubscriber($walletService, [$this->createOrder(null, Uuid::randomHex(), [])])
            ->onOrderStateChange($this->createEvent('order', Uuid::randomHex(), OrderStates::STATE_COMPLETED));
    }

    public function testConsumesWalletPaymentWhilePlacingTheOrder(): void
    {
        $customerId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $orderId = Uuid::randomHex();

        $lineItem = $this->createCreditLineItem(WalletCartProcessor::LINE_ITEM_ID, ['amount' => 30]);

        $walletService = static::createMock(WalletService::class);
        $walletService->method('getBalance')->willReturn(100.0);
        $walletService->expects($this->once())->method('consume')
            ->with($customerId, $currencyId, 30.0, $orderId, static::anything());

        $this->createSubscriber(
            $walletService,
            [$this->createOrder($customerId, $currencyId, [$lineItem], $orderId)],
        )->onOrderStateChange($this->createEvent('order', $orderId, OrderStates::STATE_COMPLETED));
    }

    public function testCapsConsumptionToAvailableBalance(): void
    {
        $customerId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $orderId = Uuid::randomHex();

        $lineItem = $this->createCreditLineItem(WalletCartProcessor::LINE_ITEM_ID, ['amount' => 150]);

        $walletService = static::createMock(WalletService::class);
        $walletService->method('getBalance')->willReturn(40.0);
        $walletService->expects($this->once())->method('consume')
            ->with($customerId, $currencyId, 40.0, $orderId, static::anything());

        $this->createSubscriber(
            $walletService,
            [$this->createOrder($customerId, $currencyId, [$lineItem], $orderId)],
        )->onOrderStateChange($this->createEvent('order', $orderId, OrderStates::STATE_COMPLETED));
    }

    public function testDoesNotConsumeWithoutPaymentPayload(): void
    {
        $walletService = static::createMock(WalletService::class);
        $walletService->expects($this->never())->method('getBalance');
        $walletService->expects($this->never())->method('consume');

        $this->createSubscriber(
            $walletService,
            [$this->createOrder(Uuid::randomHex(), Uuid::randomHex(), [])],
        )->onOrderStateChange($this->createEvent('order', Uuid::randomHex(), OrderStates::STATE_COMPLETED));
    }

    /**
     * @param list<OrderEntity> $orders
     */
    private function createSubscriber(WalletService $walletService, array $orders): WalletOrderSubscriber
    {
        return new WalletOrderSubscriber(
            StaticEntityRepository::of(OrderCollection::class, $orders === [] ? [[]] : [$orders]),
            $walletService,
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
    private function createOrder(?string $customerId, string $currencyId, array $lineItems, ?string $orderId = null): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId($orderId ?? Uuid::randomHex());
        $order->setCurrencyId($currencyId);

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
