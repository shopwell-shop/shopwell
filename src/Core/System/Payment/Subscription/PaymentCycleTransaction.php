<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Subscription;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableWriteTransaction;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Exception\PaymentSubscriptionException;

/**
 * The connection used for the row locks is also the transaction owner. The cycle and subscription
 * repositories and their state handlers use this same default connection, so every durable write of
 * the callback commits or rolls back with the locks.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\System\Payment\Subscription\PaymentCycleTransactionTest
 */
#[Package('framework')]
final class PaymentCycleTransaction implements PaymentCycleTransactionInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function execute(string $cycleId, bool $withSubscription, \Closure $callback): mixed
    {
        return RetryableWriteTransaction::retryable(
            $this->connection,
            function (Connection $connection) use ($cycleId, $withSubscription, $callback): mixed {
                $cycle = $connection->fetchAssociative(
                    'SELECT `subscription_id` FROM `payment_subscription_cycle` WHERE `id` = :id FOR UPDATE',
                    ['id' => Uuid::fromHexToBytes($cycleId)]
                );

                if ($cycle === false) {
                    throw PaymentSubscriptionException::paymentCycleNotFound($cycleId);
                }

                if ($withSubscription) {
                    $subscriptionId = Uuid::fromBytesToHex((string) $cycle['subscription_id']);
                    $locked = $connection->fetchOne(
                        'SELECT `id` FROM `payment_subscription` WHERE `id` = :id FOR UPDATE',
                        ['id' => Uuid::fromHexToBytes($subscriptionId)]
                    );

                    if ($locked === false) {
                        throw PaymentSubscriptionException::subscriptionNotFound($subscriptionId);
                    }
                }

                return $callback();
            }
        );
    }

    public function executeForSubscription(string $subscriptionId, \Closure $callback): mixed
    {
        return RetryableWriteTransaction::retryable($this->connection, function () use ($subscriptionId, $callback): mixed {
            $locked = $this->connection->fetchOne(
                'SELECT `id` FROM `payment_subscription` WHERE `id` = :id FOR UPDATE',
                ['id' => Uuid::fromHexToBytes($subscriptionId)]
            );

            if ($locked === false) {
                throw PaymentSubscriptionException::subscriptionNotFound($subscriptionId);
            }

            return $callback();
        });
    }
}
