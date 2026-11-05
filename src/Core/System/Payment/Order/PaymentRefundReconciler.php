<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentAppException;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;

/**
 * @internal
 */
#[Package('framework')]
final class PaymentRefundReconciler
{
    /**
     * @param EntityRepository<PaymentRefundCollection> $refundRepository
     * @param EntityRepository<StateMachineStateCollection> $stateRepository
     */
    public function __construct(
        private readonly EntityRepository $refundRepository,
        private readonly EntityRepository $stateRepository,
        private readonly PaymentRefundService $refundService,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function reconcileDue(int $limit = 100): int
    {
        $states = new Criteria();
        $states->addFilter(new EqualsFilter('stateMachine.technicalName', PaymentRefundStates::STATE_MACHINE));
        $states->addFilter(new EqualsAnyFilter('technicalName', [PaymentRefundStates::STATE_OPEN, PaymentRefundStates::STATE_PROCESSING]));
        $stateIds = $this->stateRepository->searchIds($states, Context::createDefaultContext())->getIds();
        if ($stateIds === []) {
            return 0;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('stateId', $stateIds));
        $criteria->addFilter(new EqualsFilter('refundedAt', null));
        $criteria->addFilter(new RangeFilter('createdAt', [
            RangeFilter::LTE => $this->clock->now()->modify('-5 minutes')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]));
        $criteria->addAssociation('paymentOrder.paymentApp');
        $criteria->addAssociation('paymentAttempt');
        // Every attempted query updates the refund. Oldest-attempt-first keeps a broken channel
        // from starving the rest of the batch indefinitely.
        $criteria->addSorting(new FieldSorting('updatedAt'), new FieldSorting('id'));
        $criteria->setLimit($limit);

        $refunds = $this->refundRepository->search($criteria, Context::createDefaultContext())->getEntities();
        $reconciled = 0;
        foreach ($refunds as $refund) {
            try {
                $order = $refund->paymentOrder;
                if ($order === null) {
                    throw PaymentOrderException::orderNotFound($refund->paymentOrderId);
                }
                if ($order->paymentApp === null) {
                    throw PaymentAppException::appNotFound($order->paymentAppId);
                }

                $this->refundService->reconcile($order->paymentApp, $order, $refund);
                ++$reconciled;
            } catch (\Throwable $exception) {
                $this->logger->warning('Reconciling the payment refund failed; its amount remains reserved.', [
                    'refundNo' => $refund->refundNo,
                    'error' => $exception->getMessage(),
                ]);
                // Record a failed query too, so this refund yields its batch position to others.
                $this->refundRepository->update([[
                    'id' => $refund->id,
                    'responsePayload' => array_replace($refund->responsePayload ?? [], ['reconciliationError' => $exception->getMessage()]),
                ]], Context::createDefaultContext());
            }
        }

        return $reconciled;
    }
}
