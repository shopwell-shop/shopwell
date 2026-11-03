<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Subscription;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Subscription\PaymentCycleTransaction;
use Shopwell\Core\System\Payment\Subscription\PaymentPlanEntity;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionCycleStates;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionStates;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
class PaymentCycleTransactionTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private Context $context;

    private string $customerId;

    private string $customerGroupId;

    private string $planId;

    private string $subscriptionId;

    private string $cycleId;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->context = Context::createDefaultContext();
        static::assertSame(0, $this->connection->getTransactionNestingLevel());

        $ids = new IdsCollection();
        $this->customerId = $ids->get('customer');
        $this->customerGroupId = $ids->get('customer-group');
        $this->planId = $ids->get('plan');
        $this->subscriptionId = $ids->get('subscription');
        $this->cycleId = $ids->get('cycle');

        $container->get('customer.repository')->create([
            (new CustomerBuilder($ids, 'customer'))->build(),
        ], $this->context);

        $container->get('payment_plan.repository')->create([[
            'id' => $this->planId,
            'technicalName' => 'cycle-lock-plan',
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'billingMode' => PaymentPlanEntity::BILLING_MODE_MERCHANT_INITIATED,
            'provider' => 'alipay',
            'channel' => 'alipay_h5',
            'intervalUnit' => PaymentPlanEntity::INTERVAL_UNIT_MONTH,
            'intervalCount' => 1,
            'amount' => 1000,
            'currencyId' => Defaults::CURRENCY,
            'trialDays' => 0,
        ]], $this->context);

        $states = $container->get(InitialStateIdLoader::class);
        $container->get('payment_subscription.repository')->create([[
            'id' => $this->subscriptionId,
            'subscriptionNo' => $this->subscriptionId,
            'planId' => $this->planId,
            'customerId' => $this->customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'billingMode' => PaymentPlanEntity::BILLING_MODE_MERCHANT_INITIATED,
            'provider' => 'alipay',
            'channel' => 'alipay_h5',
            'statusId' => $states->get(PaymentSubscriptionStates::STATE_MACHINE),
            'failureCount' => 0,
        ]], $this->context);

        $container->get('payment_subscription_cycle.repository')->create([[
            'id' => $this->cycleId,
            'subscriptionId' => $this->subscriptionId,
            'cycleNo' => 1,
            'periodStart' => new \DateTimeImmutable('2025-01-01T00:00:00+00:00'),
            'periodEnd' => new \DateTimeImmutable('2025-02-01T00:00:00+00:00'),
            'amount' => 1000,
            'currency' => 'CNY',
            'stateId' => $states->get(PaymentSubscriptionCycleStates::STATE_MACHINE),
            'outTradeNo' => $this->cycleId,
            'attemptCount' => 0,
        ]], $this->context);
    }

    protected function tearDown(): void
    {
        static::assertSame(0, $this->connection->getTransactionNestingLevel());
        $this->connection->delete('payment_subscription_cycle', ['id' => Uuid::fromHexToBytes($this->cycleId)]);
        $this->connection->delete('payment_subscription', ['id' => Uuid::fromHexToBytes($this->subscriptionId)]);
        $this->connection->delete('payment_plan', ['id' => Uuid::fromHexToBytes($this->planId)]);
        $this->connection->delete('customer', ['id' => Uuid::fromHexToBytes($this->customerId)]);
        $this->connection->delete('customer_group', ['id' => Uuid::fromHexToBytes($this->customerGroupId)]);
    }

    public function testCallbackFailureRollsBackCycleAndSubscriptionWrites(): void
    {
        $failure = new \RuntimeException('write failed');

        try {
            (new PaymentCycleTransaction($this->connection))->execute(
                $this->cycleId,
                true,
                function () use ($failure): void {
                    $this->connection->update(
                        'payment_subscription_cycle',
                        ['attempt_count' => 5],
                        ['id' => Uuid::fromHexToBytes($this->cycleId)]
                    );
                    $this->connection->update(
                        'payment_subscription',
                        ['failure_count' => 5],
                        ['id' => Uuid::fromHexToBytes($this->subscriptionId)]
                    );

                    throw $failure;
                }
            );
        } catch (\RuntimeException $exception) {
            static::assertSame($failure, $exception);
        }

        static::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT `attempt_count` FROM `payment_subscription_cycle` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($this->cycleId)]
        ));
        static::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT `failure_count` FROM `payment_subscription` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($this->subscriptionId)]
        ));
    }

    public function testCycleRowLockExcludesAnotherConnection(): void
    {
        $this->connection->beginTransaction();
        $this->connection->fetchOne(
            'SELECT `id` FROM `payment_subscription_cycle` WHERE `id` = :id FOR UPDATE',
            ['id' => Uuid::fromHexToBytes($this->cycleId)]
        );

        $callbackRan = false;

        try {
            $this->expectContention(function (Connection $connection) use (&$callbackRan): void {
                (new PaymentCycleTransaction($connection))->execute(
                    $this->cycleId,
                    true,
                    static function () use (&$callbackRan): void {
                        $callbackRan = true;
                    }
                );
            });
        } finally {
            $this->connection->rollBack();
        }

        static::assertFalse($callbackRan);
    }

    public function testSubscriptionRowLockIsTakenAfterTheCycleLock(): void
    {
        $blocker = DriverManager::getConnection($this->connection->getParams());
        $blocker->beginTransaction();
        $blocker->fetchOne(
            'SELECT `id` FROM `payment_subscription` WHERE `id` = :id FOR UPDATE',
            ['id' => Uuid::fromHexToBytes($this->subscriptionId)]
        );

        $callbackRan = false;

        try {
            $this->expectContention(function (Connection $connection) use (&$callbackRan): void {
                (new PaymentCycleTransaction($connection))->execute(
                    $this->cycleId,
                    true,
                    static function () use (&$callbackRan): void {
                        $callbackRan = true;
                    }
                );
            });
        } finally {
            if ($blocker->isTransactionActive()) {
                $blocker->rollBack();
            }
            $blocker->close();
        }

        static::assertFalse($callbackRan);
    }

    private function expectContention(\Closure $callback): void
    {
        $contender = DriverManager::getConnection($this->connection->getParams());
        $originalTimeout = (int) $contender->fetchOne('SELECT @@SESSION.innodb_lock_wait_timeout');

        try {
            $contender->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
            // An outer transaction makes this a lock assertion rather than a ten-retry test. The
            // retry behaviour itself belongs to RetryableWriteTransaction, which is shared code.
            $contender->beginTransaction();

            $this->expectException(LockWaitTimeoutException::class);
            $callback($contender);
        } finally {
            if ($contender->isTransactionActive()) {
                $contender->rollBack();
            }
            $contender->executeStatement('SET SESSION innodb_lock_wait_timeout = ' . $originalTimeout);
            $contender->close();
        }
    }
}
