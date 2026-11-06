<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Order\OrderStates;
use Shopwell\Core\Checkout\Wallet\Cart\WalletCartProcessor;
use Shopwell\Core\Checkout\Wallet\Service\WalletAccountService;
use Shopwell\Core\Checkout\Wallet\Service\WalletPaymentService;
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

        $this->createSubscriber([$this->createOrder('customer-id', Uuid::randomHex(), [])], $this->paymentsThatNeverDebit())
            ->onOrderStateChange($this->createEvent('order', Uuid::randomHex(), OrderStates::STATE_COMPLETED, $context));
    }

    public function testIgnoresEntityOtherThanOrder(): void
    {
        $this->createSubscriber([$this->createOrder('customer-id', Uuid::randomHex(), [])], $this->paymentsThatNeverDebit())
            ->onOrderStateChange($this->createEvent('customer', Uuid::randomHex(), OrderStates::STATE_COMPLETED));
    }

    public function testIgnoresStateOtherThanCompleted(): void
    {
        $this->createSubscriber([$this->createOrder('customer-id', Uuid::randomHex(), [])], $this->paymentsThatNeverDebit())
            ->onOrderStateChange($this->createEvent('order', Uuid::randomHex(), OrderStates::STATE_OPEN));
    }

    public function testIgnoresUnknownOrder(): void
    {
        $this->createSubscriber([], $this->paymentsThatNeverDebit())
            ->onOrderStateChange($this->createEvent('order', Uuid::randomHex(), OrderStates::STATE_COMPLETED));
    }

    public function testIgnoresOrderWithoutCustomer(): void
    {
        $this->createSubscriber([$this->createOrder(null, Uuid::randomHex(), [])], $this->paymentsThatNeverDebit())
            ->onOrderStateChange($this->createEvent('order', Uuid::randomHex(), OrderStates::STATE_COMPLETED));
    }

    public function testDebitsTheWalletPaymentWhilePlacingTheOrder(): void
    {
        $customerId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $orderId = Uuid::randomHex();

        $lineItem = $this->createCreditLineItem(WalletCartProcessor::LINE_ITEM_ID, ['amount' => 30]);

        $payments = $this->createMock(WalletPaymentService::class);
        $payments->expects($this->once())
            ->method('debitForOrder')
            ->with($orderId, $customerId, $currencyId, 30.0, static::anything());

        $this->createSubscriber(
            [$this->createOrder($customerId, $currencyId, [$lineItem], $orderId)],
            $payments,
            balance: 100.0,
        )->onOrderStateChange($this->createEvent('order', $orderId, OrderStates::STATE_COMPLETED));
    }

    public function testCapsTheDebitToTheAvailableBalance(): void
    {
        $customerId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $orderId = Uuid::randomHex();

        $lineItem = $this->createCreditLineItem(WalletCartProcessor::LINE_ITEM_ID, ['amount' => 150]);

        $payments = $this->createMock(WalletPaymentService::class);
        $payments->expects($this->once())
            ->method('debitForOrder')
            ->with($orderId, $customerId, $currencyId, 40.0, static::anything());

        $this->createSubscriber(
            [$this->createOrder($customerId, $currencyId, [$lineItem], $orderId)],
            $payments,
            balance: 40.0,
        )->onOrderStateChange($this->createEvent('order', $orderId, OrderStates::STATE_COMPLETED));
    }

    public function testDoesNotDebitWithoutPaymentPayload(): void
    {
        $wallets = $this->createMock(WalletAccountService::class);
        $wallets->expects($this->never())->method('balance');

        $this->createSubscriber(
            [$this->createOrder(Uuid::randomHex(), Uuid::randomHex(), [])],
            $this->paymentsThatNeverDebit(),
            $wallets,
        )->onOrderStateChange($this->createEvent('order', Uuid::randomHex(), OrderStates::STATE_COMPLETED));
    }

    private function paymentsThatNeverDebit(): WalletPaymentService&MockObject
    {
        $payments = $this->createMock(WalletPaymentService::class);
        $payments->expects($this->never())->method('debitForOrder');

        return $payments;
    }

    /**
     * @param list<OrderEntity> $orders
     */
    private function createSubscriber(
        array $orders,
        WalletPaymentService $payments,
        ?WalletAccountService $wallets = null,
        float $balance = 0.0,
    ): WalletOrderSubscriber {
        if ($wallets === null) {
            $stub = static::createStub(WalletAccountService::class);
            $stub->method('balance')->willReturn($balance);
            $wallets = $stub;
        }

        return new WalletOrderSubscriber(
            StaticEntityRepository::of(OrderCollection::class, $orders === [] ? [[]] : [$orders]),
            $wallets,
            $payments,
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
