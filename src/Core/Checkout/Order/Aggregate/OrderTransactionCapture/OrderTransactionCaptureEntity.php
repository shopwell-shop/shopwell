<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCapture;

use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class OrderTransactionCaptureEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $orderTransactionId;

    protected string $orderTransactionVersionId;

    protected string $stateId;

    protected ?string $externalReference = null;

    protected CalculatedPrice $amount;

    protected ?OrderTransactionEntity $transaction = null;

    protected ?StateMachineStateEntity $stateMachineState = null;

    protected ?OrderTransactionCaptureRefundCollection $refunds = null;

    public function getOrderTransactionId(): string
    {
        return $this->orderTransactionId;
    }

    public function setOrderTransactionId(string $orderTransactionId): void
    {
        $this->orderTransactionId = $orderTransactionId;
    }

    public function getStateId(): string
    {
        return $this->stateId;
    }

    public function setStateId(string $stateId): void
    {
        $this->stateId = $stateId;
    }

    public function getExternalReference(): ?string
    {
        return $this->externalReference;
    }

    public function setExternalReference(?string $externalReference): void
    {
        $this->externalReference = $externalReference;
    }

    public function getAmount(): CalculatedPrice
    {
        return $this->amount;
    }

    public function setAmount(CalculatedPrice $amount): void
    {
        $this->amount = $amount;
    }

    public function getTransaction(): ?OrderTransactionEntity
    {
        return $this->transaction;
    }

    public function setTransaction(?OrderTransactionEntity $transaction): void
    {
        $this->transaction = $transaction;
    }

    public function getStateMachineState(): ?StateMachineStateEntity
    {
        return $this->stateMachineState;
    }

    public function setStateMachineState(?StateMachineStateEntity $stateMachineState): void
    {
        $this->stateMachineState = $stateMachineState;
    }

    public function getRefunds(): ?OrderTransactionCaptureRefundCollection
    {
        return $this->refunds;
    }

    public function setRefunds(OrderTransactionCaptureRefundCollection $refunds): void
    {
        $this->refunds = $refunds;
    }

    public function getOrderTransactionVersionId(): string
    {
        return $this->orderTransactionVersionId;
    }

    public function setOrderTransactionVersionId(string $orderTransactionVersionId): void
    {
        $this->orderTransactionVersionId = $orderTransactionVersionId;
    }
}
