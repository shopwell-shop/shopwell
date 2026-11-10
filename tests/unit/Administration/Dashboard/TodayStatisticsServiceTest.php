<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Administration\Dashboard;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Administration\Dashboard\TodayStatisticsService;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPurchase\MembershipPurchaseCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Currency\CurrencyCollection;
use Shopwell\Core\System\Currency\CurrencyEntity;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(TodayStatisticsService::class)]
class TodayStatisticsServiceTest extends TestCase
{
    public function testTodayUsesPaidTimeDistinctOrdersAndCustomersAndYesterdaySameTime(): void
    {
        $customerId = Uuid::randomHex();
        $first = $this->payment('2026-11-09 02:10:00', 120, $customerId);
        $duplicate = clone $first;
        $duplicate->id = Uuid::randomHex();
        $duplicate->setUniqueIdentifier($duplicate->id);
        $duplicate->paidAt = new \DateTimeImmutable('2026-11-09 02:20:00');
        $second = $this->payment('2026-11-09 03:00:00', 80, $customerId);
        $yesterday = $this->payment('2026-11-08 02:10:00', 50);
        $afterCutoff = $this->payment('2026-11-08 06:00:00', 900);
        $topUp = $this->payment('2026-11-09 03:00:00', 900);
        $topUp->orderId = null;
        $topUp->order = null;
        $payments = new PaymentOrderCollection([$first, $duplicate, $second, $yesterday, $afterCutoff, $topUp]);
        $result = $this->service($payments)->load(Context::createDefaultContext(), 'Asia/Shanghai');

        static::assertSame(200.0, $result['today']['revenue']);
        static::assertSame(2, $result['today']['orders']);
        static::assertSame(1, $result['today']['customers']);
        static::assertSame(50.0, $result['previous']['revenue']);
        static::assertSame(1, $result['previous']['orders']);
        static::assertSame(120.0, $result['hours'][10]['amount']);
        static::assertSame(1, $result['hours'][10]['count']);
        static::assertNull($result['hours'][14]['amount']);
        static::assertSame('2026-11-09', $result['date']);
    }

    public function testRefundsUseCompletionTimeAndMinorCurrencyUnitsAndExcludeTopUps(): void
    {
        $payment = $this->payment('2026-10-01 00:00:00', 100);
        static::assertNotNull($payment->order);
        $payment->order->setCurrencyFactor(2);
        $refund = new PaymentRefundEntity();
        $refund->id = Uuid::randomHex();
        $refund->setUniqueIdentifier($refund->id);
        $refund->outRefundNo = Uuid::randomHex();
        $refund->paymentOrder = $payment;
        $refund->amount = 3000;
        $refund->currency = 'JPY';
        $refund->refundedAt = new \DateTimeImmutable('2026-11-09 02:00:00');
        $pending = clone $refund;
        $pending->id = Uuid::randomHex();
        $pending->setUniqueIdentifier($pending->id);
        $pending->refundedAt = null;
        $topUp = clone $refund;
        $topUp->id = Uuid::randomHex();
        $topUp->setUniqueIdentifier($topUp->id);
        $topUp->paymentOrder = clone $payment;
        $topUp->paymentOrder->orderId = null;
        $topUp->paymentOrder->order = null;
        $service = $this->service(new PaymentOrderCollection(), new PaymentRefundCollection([$refund, $pending, $topUp]));
        $result = $service->load(Context::createDefaultContext(), 'Asia/Shanghai');

        static::assertSame(1500.0, $result['today']['refundAmount']);
        static::assertSame(1, $result['today']['refundCount']);
        static::assertSame(0, $result['today']['orders']);
    }

    public function testRefundComparisonUsesYesterdayAtTheSameLocalTime(): void
    {
        $payment = $this->payment('2026-10-01 00:00:00', 100);
        $refunds = new PaymentRefundCollection();
        foreach ([['2026-11-09 02:00:00', 2000], ['2026-11-08 02:00:00', 1000], ['2026-11-08 06:00:00', 9000]] as [$date, $amount]) {
            $refund = new PaymentRefundEntity();
            $refund->id = Uuid::randomHex();
            $refund->setUniqueIdentifier($refund->id);
            $refund->outRefundNo = Uuid::randomHex();
            $refund->paymentOrder = $payment;
            $refund->amount = $amount;
            $refund->currency = 'EUR';
            $refund->refundedAt = new \DateTimeImmutable($date, new \DateTimeZone('UTC'));
            $refunds->add($refund);
        }
        $result = $this->service(new PaymentOrderCollection(), $refunds)->load(Context::createDefaultContext(), 'Asia/Shanghai');
        static::assertSame(20.0, $result['today']['refundAmount']);
        static::assertSame(10.0, $result['previous']['refundAmount']);
        static::assertSame(1, $result['previous']['refundCount']);
    }

    public function testMembershipsCountActivatedCustomersInLocalTodayAndYesterdaySameTime(): void
    {
        $membershipPurchases = StaticEntityRepository::of(MembershipPurchaseCollection::class, [
            new AggregationResultCollection([new CountResult('memberships', 5)]),
            new AggregationResultCollection([new CountResult('memberships', 2)]),
        ]);
        $result = $this->service(new PaymentOrderCollection(), membershipPurchases: $membershipPurchases)->load(Context::createDefaultContext(), 'Asia/Shanghai');
        static::assertCount(2, $membershipPurchases->searchedCriteria);
        foreach ($membershipPurchases->searchedCriteria as $index => $criteria) {
            $filters = $criteria->getFilters();
            static::assertInstanceOf(EqualsFilter::class, $filters[1]);
            static::assertInstanceOf(RangeFilter::class, $filters[0]);
            static::assertSame('state', $filters[1]->getField());
            static::assertSame('activated', $filters[1]->getValue());
            static::assertSame('activatedAt', $filters[0]->getField());
            static::assertSame('customerId', $criteria->getAggregations()['memberships']->getField());
            static::assertSame([
                'gte' => $index === 0 ? '2026-11-08 16:00:00.000' : '2026-11-07 16:00:00.000',
                'lte' => $index === 0 ? '2026-11-09 05:30:00.000' : '2026-11-08 05:30:00.000',
            ], $filters[0]->getParameters());
        }
        static::assertSame(5, $result['today']['memberships']);
        static::assertSame(2, $result['previous']['memberships']);
    }

    public function testMembershipsAreUnavailableWithoutMembershipReadAccess(): void
    {
        $membershipPurchases = StaticEntityRepository::of(MembershipPurchaseCollection::class, []);
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['order:read']);
        $result = $this->service(new PaymentOrderCollection(), membershipPurchases: $membershipPurchases)->load(new Context($source), 'UTC');
        static::assertSame([], $membershipPurchases->searchedCriteria);
        static::assertNull($result['today']['memberships']);
    }

    public function testWalletTopUpsUsePostedLocalDayAndNormalizeCurrencies(): void
    {
        $eur = new CurrencyEntity();
        $eur->setId(Defaults::CURRENCY);
        $eur->setIsoCode('EUR');
        $eur->setFactor(1);
        $usd = clone $eur;
        $usd->setId(Uuid::randomHex());
        $usd->setIsoCode('USD');
        $usd->setFactor(2);
        $entries = new CustomerWalletTransactionCollection();
        foreach ([['2026-11-08 16:00:00', 10.01, $eur], ['2026-11-09 02:00:00', 20.02, $usd], ['2026-11-08 02:00:00', 5.0, $eur]] as [$date, $amount, $currency]) {
            $entry = new CustomerWalletTransactionEntity();
            $entry->setId(Uuid::randomHex());
            $entry->setCreatedAt(new \DateTimeImmutable($date, new \DateTimeZone('UTC')));
            $entry->setAmount($amount);
            $entry->setCurrency($currency);
            $entries->add($entry);
        }
        $wallet = new StaticEntityRepository([$entries]);
        $result = $this->service(new PaymentOrderCollection(), walletTransactions: $wallet, currencies: $this->repository(new CurrencyCollection([$eur])))->load(Context::createDefaultContext(), 'Asia/Shanghai');
        static::assertCount(1, $wallet->searchedCriteria);
        $filters = $wallet->searchedCriteria[0]->getFilters();
        static::assertInstanceOf(RangeFilter::class, $filters[0]);
        static::assertSame('2026-11-07 16:00:00.000', $filters[0]->getParameters()['gte']);
        static::assertSame('2026-11-09 05:30:00.000', $filters[0]->getParameters()['lte']);
        static::assertInstanceOf(EqualsFilter::class, $filters[1]);
        static::assertSame('type', $filters[1]->getField());
        static::assertSame('top_up', $filters[1]->getValue());
        static::assertSame(20.02, $result['today']['walletTopUpAmount']);
        static::assertSame(2, $result['today']['walletTopUpCount']);
        static::assertSame(5.0, $result['previous']['walletTopUpAmount']);
        static::assertSame(1, $result['previous']['walletTopUpCount']);
    }

    public function testWalletDataIsUnavailableWithoutWalletReadAccess(): void
    {
        $wallet = StaticEntityRepository::of(CustomerWalletTransactionCollection::class, []);
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['order:read']);
        $result = $this->service(new PaymentOrderCollection(), walletTransactions: $wallet)->load(new Context($source), 'UTC');
        static::assertSame([], $wallet->searchedCriteria);
        static::assertNull($result['today']['walletTopUpAmount']);
        static::assertNull($result['today']['walletTopUpCount']);
    }

    public function testNativePaymentsDeduplicateAttemptsAndDoNotRebookEarlierSales(): void
    {
        $first = $this->payment('2026-11-09 02:00:00', 100)->order;
        $earlier = $this->payment('2026-11-01 02:00:00', 900)->order;
        static::assertNotNull($first);
        static::assertNotNull($earlier);
        $events = [];
        foreach ([$first, $earlier] as $order) {
            $transactions = new OrderTransactionCollection();
            foreach ([1, 2] as $attempt) {
                $transaction = new OrderTransactionEntity();
                $transaction->setId(Uuid::randomHex());
                $transactions->add($transaction);
                $event = new StateMachineHistoryEntity();
                $event->setId(Uuid::randomHex());
                $event->setReferencedId($transaction->getId());
                $event->setCreatedAt(new \DateTimeImmutable($order === $earlier && $attempt === 1 ? '2026-11-01 02:00:00' : '2026-11-09 02:00:00'));
                $events[] = $event;
            }
            $order->setTransactions($transactions);
        }
        $emptyPayments = new PaymentOrderCollection();
        $history = new StateMachineHistoryCollection($events);
        $service = new TodayStatisticsService(
            new StaticEntityRepository([$emptyPayments, $emptyPayments]),
            new StaticEntityRepository([new PaymentRefundCollection(), new PaymentRefundCollection()]),
            new StaticEntityRepository([$history, $history, new StateMachineHistoryCollection(), new StateMachineHistoryCollection()]),
            new StaticEntityRepository([new OrderCollection([$first, $earlier])]),
            new StaticEntityRepository([]),
            new StaticEntityRepository([new AggregationResultCollection([new CountResult('memberships', 0)]), new AggregationResultCollection([new CountResult('memberships', 0)])]),
            $this->repository(new CustomerWalletTransactionCollection()),
            $this->currencyRepository(),
            new MockClock('2026-11-09 05:30:00 UTC'),
        );
        $result = $service->load(Context::createDefaultContext(), 'Asia/Shanghai');
        static::assertSame(100.0, $result['today']['revenue']);
        static::assertSame(1, $result['today']['orders']);
        static::assertSame(1, $result['hours'][10]['count']);
    }

    public function testPendingGatewayRefundSuppressesPrematureNativeCompletion(): void
    {
        $event = new StateMachineHistoryEntity();
        $event->setId(Uuid::randomHex());
        $event->setReferencedId(Uuid::randomHex());
        $refund = new PaymentRefundEntity();
        $refund->id = Uuid::randomHex();
        $refund->setUniqueIdentifier($refund->id);
        $refund->outRefundNo = $event->getReferencedId();
        $refund->refundedAt = null;
        $refund->paymentOrder = $this->payment('2026-11-01 02:00:00', 100);
        $service = new TodayStatisticsService(
            new StaticEntityRepository([new PaymentOrderCollection()]),
            new StaticEntityRepository([new PaymentRefundCollection([$refund]), new PaymentRefundCollection()]),
            new StaticEntityRepository([new StateMachineHistoryCollection(), new StateMachineHistoryCollection([$event]), new StateMachineHistoryCollection()]),
            new StaticEntityRepository([]),
            new StaticEntityRepository([]),
            new StaticEntityRepository([new AggregationResultCollection([new CountResult('memberships', 0)]), new AggregationResultCollection([new CountResult('memberships', 0)])]),
            $this->repository(new CustomerWalletTransactionCollection()),
            $this->currencyRepository(),
            new MockClock('2026-11-09 05:30:00 UTC'),
        );
        $result = $service->load(Context::createDefaultContext(), 'Asia/Shanghai');
        static::assertSame(0.0, $result['today']['refundAmount']);
        static::assertSame(0, $result['today']['refundCount']);
    }

    public function testRecompletedNativeRefundIsOnlyCountedOnItsFirstCompletionDay(): void
    {
        $transaction = new OrderTransactionEntity();
        $order = $this->payment('2026-11-01 02:00:00', 100)->order;
        static::assertNotNull($order);
        $transaction->setOrder($order);
        $capture = new OrderTransactionCaptureEntity();
        $capture->setTransaction($transaction);
        $refunds = new OrderTransactionCaptureRefundCollection();
        $todayEvents = new StateMachineHistoryCollection();
        for ($number = 0; $number < 2; ++$number) {
            $refund = new OrderTransactionCaptureRefundEntity();
            $refund->setId(Uuid::randomHex());
            $refund->setTransactionCapture($capture);
            $refund->setAmount(new CalculatedPrice(50, 50, new CalculatedTaxCollection(), new TaxRuleCollection()));
            $refunds->add($refund);
            $event = new StateMachineHistoryEntity();
            $event->setId(Uuid::randomHex());
            $event->setReferencedId($refund->getId());
            $event->setCreatedAt(new \DateTimeImmutable('2026-11-09 02:00:00 UTC'));
            $todayEvents->add($event);
        }
        $lastEvent = $todayEvents->last();
        static::assertNotNull($lastEvent);
        $earlier = clone $lastEvent;
        $earlier->setId(Uuid::randomHex());
        $earlier->setCreatedAt(new \DateTimeImmutable('2026-11-08 02:00:00 UTC'));
        $allEvents = new StateMachineHistoryCollection([...$todayEvents, $earlier]);
        $service = new TodayStatisticsService(
            new StaticEntityRepository([new PaymentOrderCollection()]),
            new StaticEntityRepository([new PaymentRefundCollection(), new PaymentRefundCollection()]),
            new StaticEntityRepository([new StateMachineHistoryCollection(), $todayEvents, $allEvents, new StateMachineHistoryCollection()]),
            new StaticEntityRepository([]),
            $this->repository($refunds),
            new StaticEntityRepository([new AggregationResultCollection([new CountResult('memberships', 0)]), new AggregationResultCollection([new CountResult('memberships', 0)])]),
            $this->repository(new CustomerWalletTransactionCollection()),
            $this->currencyRepository(),
            new MockClock('2026-11-09 05:30:00 UTC'),
        );
        $result = $service->load(Context::createDefaultContext(), 'Asia/Shanghai');

        static::assertSame(50.0, $result['today']['refundAmount']);
        static::assertSame(1, $result['today']['refundCount']);
    }

    /**
     * @param EntityRepository<MembershipPurchaseCollection>|null $membershipPurchases
     * @param EntityRepository<CustomerWalletTransactionCollection>|null $walletTransactions
     * @param EntityRepository<CurrencyCollection>|null $currencies
     */
    private function service(PaymentOrderCollection $payments, PaymentRefundCollection $refunds = new PaymentRefundCollection(), ?EntityRepository $membershipPurchases = null, ?EntityRepository $walletTransactions = null, ?EntityRepository $currencies = null): TodayStatisticsService
    {
        if ($membershipPurchases === null) {
            $membershipPurchases = StaticEntityRepository::of(MembershipPurchaseCollection::class, [
                new AggregationResultCollection([new CountResult('memberships', 0)]),
                new AggregationResultCollection([new CountResult('memberships', 0)]),
            ]);
        }

        return new TodayStatisticsService(
            $this->repository($payments),
            $this->repository($refunds),
            $this->repository(new StateMachineHistoryCollection()),
            $this->repository(new OrderCollection(array_values(array_filter(array_map(static fn ($payment) => $payment->order, $payments->getElements()))))),
            $this->repository(new OrderTransactionCaptureRefundCollection()),
            $membershipPurchases,
            $walletTransactions ?? $this->repository(new CustomerWalletTransactionCollection()),
            $currencies ?? $this->currencyRepository(),
            new MockClock('2026-11-09 05:30:00 UTC'),
        );
    }

    /**
     * @template T of EntityCollection
     *
     * @param T $entities
     *
     * @return EntityRepository<T>
     */
    private function repository(EntityCollection $entities): EntityRepository
    {
        return new StaticEntityRepository([$entities, $entities, $entities, $entities]);
    }

    /**
     * @return EntityRepository<CurrencyCollection>
     */
    private function currencyRepository(): EntityRepository
    {
        $currency = new CurrencyEntity();
        $currency->setId(Defaults::CURRENCY);
        $currency->setIsoCode('EUR');
        $currency->setFactor(1);

        return $this->repository(new CurrencyCollection([$currency]));
    }

    private function payment(string $paidAt, float $amount, ?string $customerId = null): PaymentOrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setVersionId(Defaults::LIVE_VERSION);
        $order->setAmountTotal($amount);
        $order->setCurrencyFactor(1);
        $customer = new OrderCustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setCustomerId($customerId ?? Uuid::randomHex());
        $order->setOrderCustomer($customer);
        $payment = new PaymentOrderEntity();
        $payment->id = Uuid::randomHex();
        $payment->setUniqueIdentifier($payment->id);
        $payment->orderId = $order->getId();
        $payment->orderVersionId = Defaults::LIVE_VERSION;
        $payment->order = $order;
        $payment->paidAt = new \DateTimeImmutable($paidAt, new \DateTimeZone('UTC'));

        return $payment;
    }
}
