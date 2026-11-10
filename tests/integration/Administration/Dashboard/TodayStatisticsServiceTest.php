<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Administration\Dashboard;

use PHPUnit\Framework\TestCase;
use Shopwell\Administration\Dashboard\TodayStatisticsService;
use Shopwell\Core\Checkout\Wallet\WalletTransactionTypes;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopwell\Core\Test\Integration\Builder\Order\OrderBuilder;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('after-sales')]
class TodayStatisticsServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testWalletCardCountsOnlyPostedTopUpsInLocalToday(): void
    {
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();
        $customer = (new CustomerBuilder($ids, 'dashboard-wallet'))->build();
        static::getContainer()->get('customer.repository')->create([$customer], new Context(new SalesChannelApiSource(TestDefaults::SALES_CHANNEL)));
        $criteria = (new Criteria())->addFilter(new EqualsFilter('customerId', $customer['id']));
        $walletId = static::getContainer()->get('customer_wallet.repository')->searchIds($criteria, $context)->firstId();
        static::assertNotNull($walletId);
        $transactions = [];
        foreach ([
            [WalletTransactionTypes::TOP_UP, 10.01, '2030-01-01 16:00:00'],
            [WalletTransactionTypes::TOP_UP, 20.02, '2030-01-02 02:00:00'],
            [WalletTransactionTypes::TOP_UP, 5.0, '2030-01-01 02:00:00'],
            [WalletTransactionTypes::TOP_UP, 90.0, '2030-01-01 06:00:00'],
            [WalletTransactionTypes::TOP_UP, 100.0, '2030-01-02 06:00:00'],
            [WalletTransactionTypes::REFUND, 40.0, '2030-01-02 02:00:00'],
            [WalletTransactionTypes::ADJUST, 50.0, '2030-01-02 02:00:00'],
            [WalletTransactionTypes::CONSUME, -10.0, '2030-01-02 02:00:00'],
        ] as [$type, $amount, $createdAt]) {
            $transactions[] = ['customerWalletId' => $walletId, 'type' => $type, 'amount' => $amount, 'balanceAfter' => 0, 'currencyId' => Defaults::CURRENCY, 'createdAt' => $createdAt];
        }
        static::getContainer()->get('customer_wallet_transaction.repository')->create($transactions, $context);
        $service = new TodayStatisticsService(
            static::getContainer()->get('payment_order.repository'),
            static::getContainer()->get('payment_refund.repository'),
            static::getContainer()->get('state_machine_history.repository'),
            static::getContainer()->get('order.repository'),
            static::getContainer()->get('order_transaction_capture_refund.repository'),
            static::getContainer()->get('membership_purchase.repository'),
            static::getContainer()->get('customer_wallet_transaction.repository'),
            static::getContainer()->get('currency.repository'),
            new MockClock('2030-01-02 05:30:00 UTC'),
        );
        $result = $service->load($context, 'Asia/Shanghai');
        static::assertSame(30.03, $result['today']['walletTopUpAmount']);
        static::assertSame(2, $result['today']['walletTopUpCount']);
        static::assertSame(5.0, $result['previous']['walletTopUpAmount']);
        static::assertSame(1, $result['previous']['walletTopUpCount']);
    }

    public function testMembershipCardCountsDistinctSuccessfulActivationsByLocalDay(): void
    {
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();
        $first = (new CustomerBuilder($ids, 'dashboard-member-first'))->build();
        $second = (new CustomerBuilder($ids, 'dashboard-member-second'))->build();
        static::getContainer()->get('customer.repository')->create([$first, $second], new Context(new SalesChannelApiSource(TestDefaults::SALES_CHANNEL)));
        $purchases = [];
        foreach ([
            [$first['id'], 'activated', '2030-01-01 16:00:00'],
            [$first['id'], 'activated', '2030-01-02 02:00:00'],
            [$second['id'], 'activated', '2030-01-02 05:00:00'],
            [$first['id'], 'activated', '2030-01-01 02:00:00'],
            [$second['id'], 'activated', '2030-01-01 06:00:00'],
            [$second['id'], 'activated', '2030-01-02 06:00:00'],
            [$second['id'], 'failed', '2030-01-02 02:00:00'],
            [$second['id'], 'open', null],
        ] as [$customerId, $state, $activatedAt]) {
            $purchases[] = [
                'id' => Uuid::randomHex(),
                'customerId' => $customerId,
                'amount' => 100,
                'currencyId' => Defaults::CURRENCY,
                'currency' => 'EUR',
                'durationDays' => 30,
                'state' => $state,
                'activatedAt' => $activatedAt,
            ];
        }
        static::getContainer()->get('membership_purchase.repository')->create($purchases, $context);
        $service = new TodayStatisticsService(
            static::getContainer()->get('payment_order.repository'),
            static::getContainer()->get('payment_refund.repository'),
            static::getContainer()->get('state_machine_history.repository'),
            static::getContainer()->get('order.repository'),
            static::getContainer()->get('order_transaction_capture_refund.repository'),
            static::getContainer()->get('membership_purchase.repository'),
            static::getContainer()->get('customer_wallet_transaction.repository'),
            static::getContainer()->get('currency.repository'),
            new MockClock('2030-01-02 05:30:00 UTC'),
        );

        $result = $service->load($context, 'Asia/Shanghai');

        static::assertSame(2, $result['today']['memberships']);
        static::assertSame(1, $result['previous']['memberships']);
        static::assertArrayNotHasKey('newCustomers', $result['today']);
    }

    public function testNativePaymentHistoryCountsAnOlderOrderOnceAndUsesLocalHours(): void
    {
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();
        $order = (new OrderBuilder($ids, 'dashboard-today'))
            ->price(120)
            ->add('currencyFactor', 2)
            ->add('orderDateTime', '2020-01-01 00:00:00')
            ->addAddress('billing_address')
            ->addTransaction('transaction')
            ->addTransaction('retry')
            ->build();
        $countryId = static::getContainer()->get('country.repository')->searchIds((new Criteria())->setLimit(1), $context)->firstId();
        $order['addresses']['billing_address']['countryId'] = $countryId;
        unset($order['addresses']['billing_address']['country']);
        static::getContainer()->get('order.repository')->create([$order], $context);
        $stateCriteria = new Criteria();
        $stateCriteria->addFilter(new EqualsFilter('technicalName', 'paid'));
        $stateCriteria->addFilter(new EqualsFilter('stateMachine.technicalName', 'order_transaction.state'));
        $paidState = static::getContainer()->get('state_machine_state.repository')->search($stateCriteria, $context)->first();
        static::assertInstanceOf(StateMachineStateEntity::class, $paidState);
        foreach (['transaction', 'retry'] as $key) {
            static::getContainer()->get('state_machine_history.repository')->create([[
                'stateMachineId' => $paidState->getStateMachineId(),
                'entityName' => 'order_transaction',
                'fromStateId' => $order['transactions'][$ids->get($key)]['stateId'],
                'toStateId' => $paidState->getId(),
                'transitionActionName' => 'paid',
                'referencedId' => $ids->get($key),
                'referencedVersionId' => Defaults::LIVE_VERSION,
            ]], $context);
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Shanghai'));
        $service = new TodayStatisticsService(
            static::getContainer()->get('payment_order.repository'),
            static::getContainer()->get('payment_refund.repository'),
            static::getContainer()->get('state_machine_history.repository'),
            static::getContainer()->get('order.repository'),
            static::getContainer()->get('order_transaction_capture_refund.repository'),
            static::getContainer()->get('membership_purchase.repository'),
            static::getContainer()->get('customer_wallet_transaction.repository'),
            static::getContainer()->get('currency.repository'),
            new MockClock($now->modify('+1 minute')),
        );
        $result = $service->load($context, 'Asia/Shanghai');

        static::assertSame(1, $result['today']['orders']);
        static::assertSame(60.0, $result['today']['revenue']);
        static::assertSame(1, $result['hours'][(int) $now->format('G')]['count']);
        static::assertSame(0.0, $result['today']['refundAmount']);
    }
}
