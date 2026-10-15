<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PayResult;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundEntity;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * Turns a payment order into the answer of the api: the app never sees the ids or the internals of the
 * platform, only its own trade number and the state of the payment.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentOrderPresenter
{
    /**
     * @param EntityRepository<StateMachineStateCollection> $stateMachineStateRepository
     */
    public function __construct(
        private readonly EntityRepository $stateMachineStateRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function order(PaymentOrderEntity $order, ?PayResult $result = null): array
    {
        return [
            'payment_no' => $order->paymentNo,
            'out_trade_no' => $order->outTradeNo,
            'provider' => $order->providerTechnicalName,
            'channel' => $order->channelTechnicalName,
            'amount' => $order->amount,
            'paid_amount' => $order->paidAmount,
            'refunded_amount' => $order->refundedAmount,
            'currency' => $order->currency,
            'subject' => $order->subject,
            'attach' => $order->attach,
            'state' => $this->stateName($order->stateId),
            'out_payment_no' => $result->outPaymentNo ?? $order->outPaymentNo,
            'expire_at' => $order->expireAt?->format(\DateTimeInterface::ATOM),
            'paid_at' => $order->paidAt?->format(\DateTimeInterface::ATOM),
            'closed_at' => $order->closedAt?->format(\DateTimeInterface::ATOM),
            'pay' => $result?->params,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function refund(PaymentRefundEntity $refund): array
    {
        return [
            'refund_no' => $refund->refundNo,
            'out_refund_no' => $refund->outRefundNo,
            'amount' => $refund->amount,
            'currency' => $refund->currency,
            'reason' => $refund->reason,
            'state' => $this->stateName($refund->stateId),
            'out_refund_id' => $refund->outRefundId,
            'refunded_at' => $refund->refundedAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    public function stateName(string $stateId): ?string
    {
        $state = $this->stateMachineStateRepository
            ->search(new Criteria([$stateId]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        return $state instanceof StateMachineStateEntity ? $state->getTechnicalName() : null;
    }
}
