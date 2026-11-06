<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableWriteTransaction;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * The database concurrency boundary of a payment order and its refunds.
 *
 * Every caller uses the same connection as the DAL and the state machine. The lock order is refund
 * first and order second while settling; creating a refund only locks the order, which serializes the
 * reserved amount without ever waiting on a refund row in the opposite order.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\System\Payment\Order\PaymentOrderRowLockTest
 */
#[Package('framework')]
class PaymentOrderRowLock
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @template TReturn
     *
     * @param \Closure(): TReturn $write
     *
     * @return TReturn
     */
    public function transaction(\Closure $write)
    {
        return RetryableWriteTransaction::retryable($this->connection, static fn () => $write());
    }

    /**
     * Locks an order and reads the amounts that a refund has to use instead of its stale entity.
     *
     * @return array{paidAmount: int, refundedAmount: int}|null
     */
    public function lockOrder(string $orderId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT paid_amount, refunded_amount FROM payment_order WHERE id = :id FOR UPDATE',
            ['id' => Uuid::fromHexToBytes($orderId)],
            ['id' => ParameterType::BINARY]
        );

        if ($row === false) {
            return null;
        }

        return [
            'paidAmount' => (int) $row['paid_amount'],
            'refundedAmount' => (int) $row['refunded_amount'],
        ];
    }

    /**
     * Locks a refund before its order is locked and answers whether its amount was already booked.
     * Null means that the refund row no longer exists.
     */
    public function lockRefund(string $refundId): ?bool
    {
        $refundedAt = $this->connection->fetchOne(
            'SELECT refunded_at FROM payment_refund WHERE id = :id FOR UPDATE',
            ['id' => Uuid::fromHexToBytes($refundId)],
            ['id' => ParameterType::BINARY]
        );

        if ($refundedAt === false) {
            return null;
        }

        return $refundedAt !== null;
    }

    /**
     * Sums refunds that can still reach the channel but are not booked on the order yet.
     *
     * The caller holds the order lock, which serializes refund creation. This is deliberately not a
     * refund row lock: settlement locks refund then order, and taking the locks backwards here would
     * deadlock the two paths.
     */
    public function reservedRefundAmount(string $orderId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COALESCE(SUM(refund.amount), 0)
             FROM payment_refund refund
             INNER JOIN state_machine_state state ON state.id = refund.state_id
             WHERE refund.payment_order_id = :id
                 AND refund.refunded_at IS NULL
                 AND state.technical_name <> :failed',
            [
                'id' => Uuid::fromHexToBytes($orderId),
                'failed' => PaymentRefundStates::STATE_FAILED,
            ],
            ['id' => ParameterType::BINARY]
        );
    }
}
