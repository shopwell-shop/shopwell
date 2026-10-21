<?php declare(strict_types=1);

namespace Shopwell\Core\System\StateMachine\Aggregation\StateMachineState;

use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundCollection;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Subscription\PaymentMandateCollection;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionCollection;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionCycleCollection;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionCollection;
use Shopwell\Core\System\StateMachine\StateMachineEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class StateMachineStateEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $name = null;

    protected string $technicalName;

    protected string $stateMachineId;

    protected ?StateMachineEntity $stateMachine = null;

    protected ?StateMachineTransitionCollection $fromStateMachineTransitions = null;

    protected ?StateMachineTransitionCollection $toStateMachineTransitions = null;

    protected ?StateMachineStateTranslationCollection $translations = null;

    protected ?OrderCollection $orders = null;

    protected ?OrderTransactionCaptureCollection $orderTransactionCaptures = null;

    protected ?OrderTransactionCaptureRefundCollection $orderTransactionCaptureRefunds = null;

    protected ?OrderTransactionCollection $orderTransactions = null;

    protected ?OrderDeliveryCollection $orderDeliveries = null;

    protected ?StateMachineHistoryCollection $fromStateMachineHistoryEntries = null;

    protected ?StateMachineHistoryCollection $toStateMachineHistoryEntries = null;

    protected ?PaymentOrderCollection $paymentOrders = null;

    protected ?PaymentMandateCollection $paymentMandates = null;

    protected ?PaymentSubscriptionCollection $paymentSubscriptions = null;

    protected ?PaymentSubscriptionCycleCollection $paymentSubscriptionCycles = null;

    protected ?PaymentTransferCollection $paymentTransfers = null;

    public function getToStateMachineHistoryEntries(): ?StateMachineHistoryCollection
    {
        return $this->toStateMachineHistoryEntries;
    }

    public function setToStateMachineHistoryEntries(StateMachineHistoryCollection $toStateMachineHistoryEntries): void
    {
        $this->toStateMachineHistoryEntries = $toStateMachineHistoryEntries;
    }

    public function getFromStateMachineHistoryEntries(): ?StateMachineHistoryCollection
    {
        return $this->fromStateMachineHistoryEntries;
    }

    public function setFromStateMachineHistoryEntries(StateMachineHistoryCollection $fromStateMachineHistoryEntries): void
    {
        $this->fromStateMachineHistoryEntries = $fromStateMachineHistoryEntries;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getStateMachineId(): string
    {
        return $this->stateMachineId;
    }

    public function setStateMachineId(string $stateMachineId): void
    {
        $this->stateMachineId = $stateMachineId;
    }

    public function getStateMachine(): ?StateMachineEntity
    {
        return $this->stateMachine;
    }

    public function setStateMachine(StateMachineEntity $stateMachine): void
    {
        $this->stateMachine = $stateMachine;
    }

    public function getFromStateMachineTransitions(): ?StateMachineTransitionCollection
    {
        return $this->fromStateMachineTransitions;
    }

    public function setFromStateMachineTransitions(StateMachineTransitionCollection $fromStateMachineTransitions): void
    {
        $this->fromStateMachineTransitions = $fromStateMachineTransitions;
    }

    public function getToStateMachineTransitions(): ?StateMachineTransitionCollection
    {
        return $this->toStateMachineTransitions;
    }

    public function setToStateMachineTransitions(StateMachineTransitionCollection $toStateMachineTransitions): void
    {
        $this->toStateMachineTransitions = $toStateMachineTransitions;
    }

    public function getTranslations(): ?StateMachineStateTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(StateMachineStateTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getOrders(): ?OrderCollection
    {
        return $this->orders;
    }

    public function setOrders(OrderCollection $orders): void
    {
        $this->orders = $orders;
    }

    public function getOrderTransactionCaptures(): ?OrderTransactionCaptureCollection
    {
        return $this->orderTransactionCaptures;
    }

    public function setOrderTransactionCaptures(OrderTransactionCaptureCollection $orderTransactionCaptures): void
    {
        $this->orderTransactionCaptures = $orderTransactionCaptures;
    }

    public function getOrderTransactionCaptureRefunds(): ?OrderTransactionCaptureRefundCollection
    {
        return $this->orderTransactionCaptureRefunds;
    }

    public function setOrderTransactionCaptureRefunds(OrderTransactionCaptureRefundCollection $orderTransactionCaptureRefunds): void
    {
        $this->orderTransactionCaptureRefunds = $orderTransactionCaptureRefunds;
    }

    public function getOrderTransactions(): ?OrderTransactionCollection
    {
        return $this->orderTransactions;
    }

    public function setOrderTransactions(OrderTransactionCollection $orderTransactions): void
    {
        $this->orderTransactions = $orderTransactions;
    }

    public function getOrderDeliveries(): ?OrderDeliveryCollection
    {
        return $this->orderDeliveries;
    }

    public function setOrderDeliveries(OrderDeliveryCollection $orderDeliveries): void
    {
        $this->orderDeliveries = $orderDeliveries;
    }

    public function getPaymentOrders(): ?PaymentOrderCollection
    {
        return $this->paymentOrders;
    }

    public function setPaymentOrders(PaymentOrderCollection $paymentOrders): void
    {
        $this->paymentOrders = $paymentOrders;
    }

    public function getPaymentMandates(): ?PaymentMandateCollection
    {
        return $this->paymentMandates;
    }

    public function setPaymentMandates(PaymentMandateCollection $paymentMandates): void
    {
        $this->paymentMandates = $paymentMandates;
    }

    public function getPaymentSubscriptions(): ?PaymentSubscriptionCollection
    {
        return $this->paymentSubscriptions;
    }

    public function setPaymentSubscriptions(PaymentSubscriptionCollection $paymentSubscriptions): void
    {
        $this->paymentSubscriptions = $paymentSubscriptions;
    }

    public function getPaymentSubscriptionCycles(): ?PaymentSubscriptionCycleCollection
    {
        return $this->paymentSubscriptionCycles;
    }

    public function setPaymentSubscriptionCycles(PaymentSubscriptionCycleCollection $paymentSubscriptionCycles): void
    {
        $this->paymentSubscriptionCycles = $paymentSubscriptionCycles;
    }

    public function getPaymentTransfers(): ?PaymentTransferCollection
    {
        return $this->paymentTransfers;
    }

    public function setPaymentTransfers(PaymentTransferCollection $paymentTransfers): void
    {
        $this->paymentTransfers = $paymentTransfers;
    }
}
