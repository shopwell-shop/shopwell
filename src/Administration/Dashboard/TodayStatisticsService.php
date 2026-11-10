<?php declare(strict_types=1);

namespace Shopwell\Administration\Dashboard;

use Shopwell\Core\Checkout\Membership\Entity\MembershipPurchase\MembershipPurchaseCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPurchase\MembershipPurchaseEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundCollection;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionCollection;
use Shopwell\Core\Checkout\Wallet\WalletTransactionTypes;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyAmountConverter;
use Shopwell\Core\System\Currency\CurrencyCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryCollection;
use Symfony\Component\Clock\ClockInterface;

/**
 * @internal
 *
 * @phpstan-type Totals array{revenue: float, orders: int, customers: int, refundAmount: float, refundCount: int, memberships: int|null, walletTopUpAmount: float|null, walletTopUpCount: int|null}
 * @phpstan-type Summary array{date: string, today: Totals, previous: Totals, hours: list<array{hour: int, amount: float|null, count: int|null}>}
 */
#[Package('after-sales')]
class TodayStatisticsService
{
    /**
     * @param EntityRepository<PaymentOrderCollection> $payments
     * @param EntityRepository<PaymentRefundCollection> $refunds
     * @param EntityRepository<StateMachineHistoryCollection> $history
     * @param EntityRepository<OrderCollection> $orders
     * @param EntityRepository<OrderTransactionCaptureRefundCollection> $captureRefunds
     * @param EntityRepository<MembershipPurchaseCollection> $membershipPurchases
     * @param EntityRepository<CustomerWalletTransactionCollection> $walletTransactions
     * @param EntityRepository<CurrencyCollection> $currencies
     */
    public function __construct(
        private readonly EntityRepository $payments,
        private readonly EntityRepository $refunds,
        private readonly EntityRepository $history,
        private readonly EntityRepository $orders,
        private readonly EntityRepository $captureRefunds,
        private readonly EntityRepository $membershipPurchases,
        private readonly EntityRepository $walletTransactions,
        private readonly EntityRepository $currencies,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return Summary
     */
    public function load(Context $context, string $timezone): array
    {
        $now = $this->clock->now()->setTimezone(new \DateTimeZone($timezone));
        $today = $now->setTime(0, 0);
        $yesterday = $today->modify('-1 day');
        $previousEnd = $now->modify('-1 day');
        $range = $this->range('paidAt', $yesterday, $now);
        $range->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('orderId', null)]));
        $range->addFilter(new EqualsFilter('orderVersionId', Defaults::LIVE_VERSION));
        $candidates = $this->read($this->payments, $range, $context);
        $history = $this->read($this->history, $this->historyCriteria('order_transaction', 'paid', $yesterday, $now), $context);
        $orderIds = [];
        foreach ($candidates as $payment) {
            if ($payment->orderId !== null) {
                $orderIds[] = $payment->orderId;
            }
        }
        $transactionIds = array_map(static fn ($event) => $event->getReferencedId(), $history);
        $orderCriteria = new Criteria();
        $orderCriteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsAnyFilter('id', $orderIds),
            new EqualsAnyFilter('transactions.id', $transactionIds),
        ]));
        $orderCriteria->addAssociation('orderCustomer');
        $orderCriteria->addAssociation('transactions');
        $orders = ($orderIds !== [] || $transactionIds !== []) ? $this->read($this->orders, $orderCriteria, $context) : [];
        $firstPaid = [];
        if ($orders !== []) {
            $ids = array_map(static fn (OrderEntity $order) => $order->getId(), $orders);
            $paymentCriteria = new Criteria();
            $paymentCriteria->addFilter(new EqualsAnyFilter('orderId', $ids));
            $paymentCriteria->addFilter(new EqualsFilter('orderVersionId', Defaults::LIVE_VERSION));
            $paymentCriteria->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('paidAt', null)]));
            foreach ($this->read($this->payments, $paymentCriteria, $context) as $payment) {
                if ($payment->orderId !== null && $payment->paidAt !== null) {
                    $this->earliest($firstPaid, $payment->orderId, $payment->paidAt);
                }
            }
            $transactionOrders = [];
            foreach ($orders as $order) {
                foreach ($order->getTransactions() ?? [] as $transaction) {
                    $transactionOrders[$transaction->getId()] = $order->getId();
                }
            }
            if ($transactionOrders !== []) {
                $criteria = $this->historyCriteria('order_transaction', 'paid');
                $criteria->addFilter(new EqualsAnyFilter('referencedId', array_keys($transactionOrders)));
                foreach ($this->read($this->history, $criteria, $context) as $event) {
                    if (isset($transactionOrders[$event->getReferencedId()]) && $event->getCreatedAt() !== null) {
                        $this->earliest($firstPaid, $transactionOrders[$event->getReferencedId()], $event->getCreatedAt());
                    }
                }
            }
        }

        $totals = ['revenue' => 0.0, 'orders' => 0, 'customers' => 0, 'refundAmount' => 0.0, 'refundCount' => 0, 'memberships' => null, 'walletTopUpAmount' => null, 'walletTopUpCount' => null];
        $result = ['date' => $today->format('Y-m-d'), 'today' => $totals, 'previous' => $totals, 'hours' => []];
        for ($hour = 0; $hour < 24; ++$hour) {
            $future = $hour > (int) $now->format('G');
            $result['hours'][] = ['hour' => $hour, 'amount' => $future ? null : 0.0, 'count' => $future ? null : 0];
        }
        $customers = ['today' => [], 'previous' => []];
        foreach ($orders as $order) {
            $paidAt = $firstPaid[$order->getId()] ?? null;
            if ($paidAt === null) {
                continue;
            }
            $period = $paidAt >= $today && $paidAt <= $now ? 'today' : ($paidAt >= $yesterday && $paidAt <= $previousEnd ? 'previous' : null);
            if ($period === null) {
                continue;
            }
            $amount = $this->normalize($order->getAmountTotal(), $order);
            $result[$period]['revenue'] += $amount;
            ++$result[$period]['orders'];
            $customerId = $order->getOrderCustomer()?->getCustomerId();
            if ($customerId !== null) {
                $customers[$period][$customerId] = true;
            }
            if ($period === 'today') {
                $hour = (int) \DateTimeImmutable::createFromInterface($paidAt)->setTimezone($now->getTimezone())->format('G');
                $result['hours'][$hour] = [
                    'hour' => $hour,
                    'amount' => ($result['hours'][$hour]['amount'] ?? 0) + $amount,
                    'count' => ($result['hours'][$hour]['count'] ?? 0) + 1,
                ];
            }
        }
        foreach ($customers as $period => $ids) {
            $result[$period]['customers'] = \count($ids);
            $result[$period]['revenue'] = round($result[$period]['revenue'], 2);
        }
        foreach ($result['hours'] as &$bucket) {
            if ($bucket['amount'] !== null) {
                $bucket['amount'] = round($bucket['amount'], 2);
            }
        }
        unset($bucket);
        [$result['today']['refundAmount'], $result['today']['refundCount']] = $this->loadRefunds($context, $today, $now);
        [$result['previous']['refundAmount'], $result['previous']['refundCount']] = $this->loadRefunds($context, $yesterday, $previousEnd);

        $source = $context->getSource();
        if (!$source instanceof AdminApiSource || $source->isAllowed('membership_purchase:read')) {
            $result['today']['memberships'] = $this->countMemberships($context, $today, $now);
            $result['previous']['memberships'] = $this->countMemberships($context, $yesterday, $previousEnd);
        }

        if (!$source instanceof AdminApiSource || $source->isAllowed('customer_wallet_transaction:read')) {
            $walletTotals = $this->loadWalletTopUps($context, $yesterday, $today, $previousEnd, $now);
            foreach ($walletTotals as $period => $wallet) {
                $result[$period]['walletTopUpAmount'] = $wallet['amount'];
                $result[$period]['walletTopUpCount'] = $wallet['count'];
            }
        }

        return $result;
    }

    /**
     * @return array{today: array{amount: float|null, count: int|null}, previous: array{amount: float|null, count: int|null}}
     */
    private function loadWalletTopUps(Context $context, \DateTimeImmutable $yesterday, \DateTimeImmutable $today, \DateTimeImmutable $previousEnd, \DateTimeImmutable $now): array
    {
        $unavailable = ['today' => ['amount' => null, 'count' => null], 'previous' => ['amount' => null, 'count' => null]];
        $systemCurrency = $this->currencies->search(new Criteria([Defaults::CURRENCY]), $context)->getEntities()->first();
        if ($systemCurrency === null) {
            return $unavailable;
        }
        $criteria = $this->range('createdAt', $yesterday, $now);
        $criteria->addFilter(new EqualsFilter('type', WalletTransactionTypes::TOP_UP));
        $criteria->addAssociation('currency');
        $criteria->addAssociation('wallet.currency');
        $totals = ['today' => ['amount' => 0, 'count' => 0], 'previous' => ['amount' => 0, 'count' => 0]];
        foreach ($this->read($this->walletTransactions, $criteria, $context) as $transaction) {
            $date = $transaction->getCreatedAt();
            $period = $date !== null && $date >= $today && $date <= $now ? 'today' : ($date !== null && $date >= $yesterday && $date <= $previousEnd ? 'previous' : null);
            if ($period === null) {
                continue;
            }
            $currency = $transaction->getCurrency() ?? $transaction->getWallet()?->getCurrency();
            if ($currency === null || $currency->getFactor() <= 0) {
                return $unavailable;
            }
            // The ledger has no exchange-rate snapshot: use the current factor and accumulate system minor units.
            $totals[$period]['amount'] += CurrencyAmountConverter::fromFloat($transaction->getAmount() / $currency->getFactor(), $systemCurrency->getIsoCode());
            ++$totals[$period]['count'];
        }

        return [
            'today' => ['amount' => CurrencyAmountConverter::toFloat($totals['today']['amount'], $systemCurrency->getIsoCode()), 'count' => $totals['today']['count']],
            'previous' => ['amount' => CurrencyAmountConverter::toFloat($totals['previous']['amount'], $systemCurrency->getIsoCode()), 'count' => $totals['previous']['count']],
        ];
    }

    private function countMemberships(Context $context, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $criteria = $this->range('activatedAt', $from, $to);
        $criteria->addFilter(new EqualsFilter('state', MembershipPurchaseEntity::STATE_ACTIVATED));
        $criteria->addAggregation(new CountAggregation('memberships', 'customerId'));
        $count = $this->membershipPurchases->aggregate($criteria, $context)->get('memberships');

        return $count instanceof CountResult ? $count->getCount() : 0;
    }

    /**
     * @return array{float, int}
     */
    private function loadRefunds(Context $context, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $events = $this->read($this->history, $this->historyCriteria('order_transaction_capture_refund', 'completed', $from, $to), $context);
        $nativeIds = array_values(array_unique(array_map(static fn ($event) => $event->getReferencedId(), $events)));
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new RangeFilter('refundedAt', $this->bounds($from, $to)),
            new EqualsAnyFilter('outRefundNo', $nativeIds),
        ]));
        $criteria->addAssociation('paymentOrder.order');
        $amounts = [];
        $gatewayIds = [];
        foreach ($this->read($this->refunds, $criteria, $context) as $refund) {
            $gatewayIds[$refund->outRefundNo] = true;
            $order = $refund->paymentOrder?->order;
            if ($order === null || $refund->paymentOrder?->orderVersionId !== Defaults::LIVE_VERSION || $refund->refundedAt === null || $refund->refundedAt < $from || $refund->refundedAt > $to) {
                continue;
            }
            $amounts['gateway-' . $refund->id] = $this->normalize(CurrencyAmountConverter::toFloat($refund->amount, $refund->currency), $order);
        }
        $nativeIds = array_values(array_filter($nativeIds, static fn (string $id) => !isset($gatewayIds[$id])));
        if ($nativeIds !== []) {
            $criteria = $this->historyCriteria('order_transaction_capture_refund', 'completed');
            $criteria->addFilter(new EqualsAnyFilter('referencedId', $nativeIds));
            $firstCompleted = [];
            foreach ($this->read($this->history, $criteria, $context) as $event) {
                if ($event->getCreatedAt() !== null) {
                    $this->earliest($firstCompleted, $event->getReferencedId(), $event->getCreatedAt());
                }
            }
            $criteria = new Criteria($nativeIds);
            $criteria->addAssociation('transactionCapture.transaction.order');
            foreach ($this->read($this->captureRefunds, $criteria, $context) as $refund) {
                $completedAt = $firstCompleted[$refund->getId()] ?? null;
                if ($completedAt === null || $completedAt < $from || $completedAt > $to) {
                    continue;
                }
                $order = $refund->getTransactionCapture()?->getTransaction()?->getOrder();
                if ($order !== null) {
                    $amounts[$refund->getId()] = $this->normalize($refund->getAmount()->getTotalPrice(), $order);
                }
            }
        }

        return [round(array_sum($amounts), 2), \count($amounts)];
    }

    private function normalize(float $amount, OrderEntity $order): float
    {
        return $amount / ($order->getCurrencyFactor() > 0 ? $order->getCurrencyFactor() : 1);
    }

    /**
     * @param array<string, \DateTimeInterface> $dates
     */
    private function earliest(array &$dates, string $id, \DateTimeInterface $date): void
    {
        if (!isset($dates[$id]) || $date < $dates[$id]) {
            $dates[$id] = $date;
        }
    }

    private function historyCriteria(string $entity, string $state, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('entityName', $entity));
        $criteria->addFilter(new EqualsFilter('referencedVersionId', Defaults::LIVE_VERSION));
        $criteria->addFilter(new EqualsFilter('toStateMachineState.technicalName', $state));
        if ($from !== null && $to !== null) {
            $criteria->addFilter(new RangeFilter('createdAt', $this->bounds($from, $to)));
        }

        return $criteria;
    }

    private function range(string $field, \DateTimeImmutable $from, \DateTimeImmutable $to): Criteria
    {
        return (new Criteria())->addFilter(new RangeFilter($field, $this->bounds($from, $to)));
    }

    /**
     * @return array{gte: string, lte: string}
     */
    private function bounds(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $utc = new \DateTimeZone('UTC');

        return ['gte' => $from->setTimezone($utc)->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'lte' => $to->setTimezone($utc)->format(Defaults::STORAGE_DATE_TIME_FORMAT)];
    }

    /**
     * @template T of EntityCollection
     *
     * @param EntityRepository<T> $repository
     *
     * @return list<value-of<T>>
     */
    private function read(EntityRepository $repository, Criteria $criteria, Context $context): array
    {
        $result = [];
        $criteria->setLimit(500);
        $criteria->addSorting(new FieldSorting('id'));
        do {
            $entities = $repository->search($criteria, $context)->getEntities();
            array_push($result, ...array_values($entities->getElements()));
            $criteria->setOffset(($criteria->getOffset() ?? 0) + 500);
        } while ($entities->count() === 500);

        return $result;
    }
}
