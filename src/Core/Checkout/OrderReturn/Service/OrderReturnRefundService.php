<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Service;

use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PaymentRefundRequest;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundService;

/**
 * Pays a return out through the payment gateway.
 *
 * The refund is not re-implemented here: the gateway already knows how to refund a payment order
 * idempotently, how much of it is still refundable and which interfaces can refund at all. This
 * service only answers the two questions the gateway can not - which payment belongs to the order of
 * the return and whose tenant collected it - and writes the resulting refund number back to the
 * return.
 *
 * The refund number of the aftersale is used as the out refund number of the gateway. It is unique
 * per return and never changes, so a retried payout (a timeout of the channel, a clicked button
 * twice) can not refund the customer twice: the gateway answers the replay with the refund it already
 * has.
 *
 * @final
 */
#[Package('after-sales')]
class OrderReturnRefundService
{
    /**
     * @internal
     *
     * @param EntityRepository<PaymentAppCollection> $paymentAppRepository
     * @param EntityRepository<OrderReturnCollection> $orderReturnRepository
     */
    public function __construct(
        private readonly EntityRepository $paymentAppRepository,
        private readonly EntityRepository $orderReturnRepository,
        private readonly PaymentOrderService $paymentOrderService,
        private readonly PaymentRefundService $paymentRefundService,
        private readonly OrderReturnStateHandler $stateHandler,
    ) {
    }

    /**
     * Hands the amount of the return to the gateway and books the result on the return.
     *
     * A channel that answers asynchronously leaves the return in `refunding`; the subscriber on
     * `PaymentRefundFinishedEvent` closes it once the channel confirms. A channel that answers
     * synchronously is closed right away.
     *
     * @throws OrderReturnException when there is no payment to refund or the gateway refuses
     */
    public function refund(OrderReturnEntity $orderReturn, OrderEntity $order, Context $context): void
    {
        if ($orderReturn->paymentRefundNo !== null) {
            // the payout was already started for this return, the gateway answers a replay itself -
            // calling it again here would only write the same number a second time
            return;
        }

        if ($orderReturn->refundAmount <= 0) {
            throw OrderReturnException::refundNotPossible($orderReturn->returnNumber, 'the return has no amount to refund.');
        }

        $paymentOrder = $this->paymentOrder($order);

        if ($paymentOrder === null) {
            throw OrderReturnException::refundNotPossible(
                $orderReturn->returnNumber,
                'the order has no collected payment.'
            );
        }

        $app = $this->paymentApp($paymentOrder);

        if ($app === null) {
            throw OrderReturnException::refundNotPossible(
                $orderReturn->returnNumber,
                'the payment app of the collected payment does not exist anymore.'
            );
        }

        $refund = $this->paymentRefundService->refund(
            $app,
            $paymentOrder,
            new PaymentRefundRequest(
                outRefundNo: $orderReturn->returnNumber,
                amount: $orderReturn->refundAmount,
                reason: 'Return ' . $orderReturn->returnNumber,
            )
        );

        // the number is written before the state moves: the subscriber that closes the return when the
        // channel confirms finds the return by exactly this number
        $this->orderReturnRepository->update([[
            'id' => $orderReturn->id,
            'paymentRefundNo' => $refund->getRefundNo(),
        ]], $context);

        // the return moves to refunding only after the gateway accepted the request, a refused request
        // leaves it where it was so a merchant can correct the amount or the channel and retry
        $this->stateHandler->refund($orderReturn->id, $context);

        if ($refund->getRefundedAt() !== null) {
            $this->stateHandler->finish($orderReturn->id, $context);
        }
    }

    /**
     * The payment that collected the order of the return.
     *
     * Only a collected payment can be refunded, so the latest payment of the primary transaction is
     * taken and checked for it. An order that was never paid (an invoice, a manual order) has none,
     * and a merchant has to pay that one out by hand - which is the honest answer and not something a
     * gateway call could invent.
     */
    private function paymentOrder(OrderEntity $order): ?PaymentOrderEntity
    {
        $transactionId = $order->getPrimaryOrderTransactionId();

        if ($transactionId === null) {
            return null;
        }

        $paymentOrder = $this->paymentOrderService->findLatestByOrderTransaction($transactionId);

        if (!$paymentOrder instanceof PaymentOrderEntity || $paymentOrder->getPaidAt() === null) {
            return null;
        }

        return $paymentOrder;
    }

    private function paymentApp(PaymentOrderEntity $paymentOrder): ?PaymentAppEntity
    {
        $criteria = new Criteria([$paymentOrder->getPaymentAppId()]);

        $app = $this->paymentAppRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        return $app instanceof PaymentAppEntity ? $app : null;
    }
}
