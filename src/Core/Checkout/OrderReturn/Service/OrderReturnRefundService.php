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
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentRefundService;
use Shopwell\Core\System\Payment\Order\Struct\PaymentRefundRequest;

/**
 * Pays a return out.
 *
 * The refund is not re-implemented here: the gateway already knows how to refund a payment order
 * idempotently, how much of it is still refundable and which interfaces can refund at all. This
 * service decides how the amount is composed - which part is given back by a
 * {@see OrderReturnRefundSource} and which part the gateway pays - and writes the resulting refund
 * number back to the return.
 *
 * The gateway only sees what it collected itself. An order that was paid partly with the wallet is
 * therefore refunded in two steps: the wallet part first, the rest at the gateway. Asking the gateway
 * for the full amount would make it refuse the payout, because a payment order can never refund more
 * than it was paid.
 *
 * The refund number of the aftersale is used as the out refund number of the gateway. It is unique
 * per return and never changes, so a retried payout (a timeout of the channel, a clicked button
 * twice) can not refund the customer twice: the gateway answers the replay with the refund it already
 * has, and a refund source skips a reference it has already booked.
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
     * @param iterable<OrderReturnRefundSource> $sources
     */
    public function __construct(
        private readonly EntityRepository $paymentAppRepository,
        private readonly EntityRepository $orderReturnRepository,
        private readonly PaymentOrderService $paymentOrderService,
        private readonly PaymentRefundService $paymentRefundService,
        private readonly OrderReturnStateHandler $stateHandler,
        private readonly OrderReturnRefundExecutor $executor,
        private readonly iterable $sources = [],
    ) {
    }

    /**
     * Pays the amount of the return out and books the result on the return.
     *
     * A channel that answers asynchronously leaves the return in `refunding`; the subscriber on
     * `PaymentRefundFinishedEvent` closes it once the channel confirms. A channel that answers
     * synchronously - and a return the sources paid completely, which never reaches the channel - is
     * closed right away.
     *
     * @throws OrderReturnException when the return has nothing to pay out or the gateway refuses
     */
    public function refund(OrderReturnEntity $orderReturn, OrderEntity $order, Context $context): void
    {
        $this->executor->run($orderReturn, $order, function () use ($orderReturn, $order, $context): void {
            $this->refundLocked($orderReturn, $order, $context);
        });
    }

    private function refundLocked(OrderReturnEntity $orderReturn, OrderEntity $order, Context $context): void
    {
        if ($orderReturn->refundAmount <= 0) {
            throw OrderReturnException::refundNotPossible($orderReturn->returnNumber, 'the return has no amount to refund.');
        }

        if ($orderReturn->paymentRefundNo !== null) {
            // the payout of this return has already run: the gateway has a refund under its number and
            // the sources have booked against it. A second call must not compose the amount again -
            // a source that answers "500 refundable" once more would pay the customer twice as soon as
            // its own idempotency lapses. Money that is out wins over a state that a crash left behind.
            return;
        }

        [$remaining, $portions] = $this->split($orderReturn, $order, $context);

        if ($orderReturn->refundDestination === OrderReturnEntity::REFUND_WALLET) {
            $destination = null;
            foreach ($this->sources as $source) {
                if ($source instanceof OrderReturnRefundDestination && $source->getDestination() === OrderReturnEntity::REFUND_WALLET) {
                    $destination = $source;
                    break;
                }
            }

            if ($destination === null) {
                throw OrderReturnException::refundNotPossible($orderReturn->returnNumber, 'the wallet refund destination is unavailable.');
            }

            $redirected = $remaining;
            $originalPortions = [];
            foreach ($portions as [$source, $amount]) {
                if ($source === $destination) {
                    $redirected += $amount;
                } else {
                    $originalPortions[] = [$source, $amount];
                }
            }

            if ($redirected > $destination->refundableDestinationAmount($orderReturn, $order, $context)) {
                throw OrderReturnException::refundNotPossible($orderReturn->returnNumber, 'the collected amount cannot be credited to the customer wallet.');
            }
            if ($redirected > 0) {
                $originalPortions[] = [$destination, $redirected];
            }
            $portions = $originalPortions;
            $remaining = 0;
        }

        // resolved before any money moves: a return that cannot be paid out completely has to fail while
        // it is still untouched, not after a source has already credited the customer
        $gateway = $remaining > 0 ? $this->gateway($orderReturn, $order) : null;

        foreach ($portions as [$source, $amount]) {
            $source->refund($orderReturn, $order, $amount, $context);
        }

        $synchronous = $gateway === null || $this->refundAtGateway($orderReturn, $gateway, $remaining, $context);

        // the return moves to refunding only after the money was accepted, a refused payout leaves it
        // where it was so a merchant can correct the amount or the channel and retry
        $this->stateHandler->refund($orderReturn->id, $context);

        if ($synchronous) {
            $this->stateHandler->finish($orderReturn->id, $context);
        }
    }

    /**
     * Splits the amount of the return over the sources that collected part of the order.
     *
     * A source is asked for what it still holds, never for more, and never for more than the return is
     * worth: the second return of the same order therefore only gets the part the first one left over.
     *
     * @return array{int, list<array{OrderReturnRefundSource, int}>} what is left for the gateway and the portions of the sources
     */
    private function split(OrderReturnEntity $orderReturn, OrderEntity $order, Context $context): array
    {
        $remaining = $orderReturn->refundAmount;
        $portions = [];

        foreach ($this->sources as $source) {
            if ($remaining <= 0) {
                break;
            }

            $amount = min($remaining, $source->refundableAmount($orderReturn, $order, $context));

            if ($amount <= 0) {
                continue;
            }

            $portions[] = [$source, $amount];
            $remaining -= $amount;
        }

        return [$remaining, $portions];
    }

    /**
     * The payment that collected the order of the return, and the channel it was collected with.
     *
     * Only a collected payment can be refunded, so the latest payment of the primary transaction is
     * taken and checked for it. An order that was never paid (an invoice, a manual order) has none,
     * and a merchant has to pay that one out by hand - which is the honest answer and not something a
     * gateway call could invent.
     *
     * @throws OrderReturnException
     *
     * @return array{PaymentAppEntity, PaymentOrderEntity}
     */
    private function gateway(OrderReturnEntity $orderReturn, OrderEntity $order): array
    {
        $transactionId = $order->getPrimaryOrderTransactionId();

        $paymentOrder = $transactionId === null
            ? null
            : $this->paymentOrderService->findLatestByOrderTransaction($transactionId);

        if (!$paymentOrder instanceof PaymentOrderEntity || $paymentOrder->paidAt === null) {
            throw OrderReturnException::refundNotPossible($orderReturn->returnNumber, 'the order has no collected payment.');
        }

        $criteria = new Criteria([$paymentOrder->paymentAppId]);
        $app = $this->paymentAppRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        if (!$app instanceof PaymentAppEntity) {
            throw OrderReturnException::refundNotPossible(
                $orderReturn->returnNumber,
                'the payment app of the collected payment does not exist anymore.'
            );
        }

        return [$app, $paymentOrder];
    }

    /**
     * @param array{PaymentAppEntity, PaymentOrderEntity} $gateway
     *
     * @return bool whether the channel answered synchronously
     */
    private function refundAtGateway(OrderReturnEntity $orderReturn, array $gateway, int $amount, Context $context): bool
    {
        [$app, $paymentOrder] = $gateway;

        $refund = $this->paymentRefundService->refund(
            $app,
            $paymentOrder,
            new PaymentRefundRequest(
                outRefundNo: $orderReturn->returnNumber,
                amount: $amount,
                reason: 'Return ' . $orderReturn->returnNumber,
            )
        );

        // the number is written before the state moves: the subscriber that closes the return when the
        // channel confirms finds the return by exactly this number
        $this->orderReturnRepository->update([[
            'id' => $orderReturn->id,
            'paymentRefundNo' => $refund->refundNo,
        ]], $context);

        return $refund->refundedAt !== null;
    }
}
