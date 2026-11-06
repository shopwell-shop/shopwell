<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Cashier;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableWriteTransaction;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * Serializes the short session-to-order binding. Channel calls must run after this transaction commits.
 *
 * @internal
 */
#[Package('framework')]
class PaymentCashierSessionLock
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @template T
     *
     * @param \Closure(): T $write
     *
     * @return T
     */
    public function transaction(string $sessionId, \Closure $write): mixed
    {
        return RetryableWriteTransaction::retryable($this->connection, function () use ($sessionId, $write): mixed {
            $this->connection->fetchOne(
                'SELECT id FROM payment_cashier_session WHERE id = :id FOR UPDATE',
                ['id' => Uuid::fromHexToBytes($sessionId)],
                ['id' => ParameterType::BINARY],
            );

            return $write();
        });
    }
}
