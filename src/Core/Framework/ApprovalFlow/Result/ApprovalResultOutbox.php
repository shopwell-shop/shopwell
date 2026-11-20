<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Result;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * Owns committed delivery leases, independently of the completed approval instance.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Result\ApprovalResultOutboxTest
 */
#[Package('framework')]
class ApprovalResultOutbox
{
    public const LEASE_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    private const RETRY_DELAYS = [5, 30, 300, 1800, 14400];

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @phpstan-impure Claimability changes as work is completed or time advances.
     */
    public function claim(Context $context): ?ApprovalResultDelivery
    {
        $this->requireDeliveryContext($context);

        return RetryableTransaction::transactional($this->connection, function (): ?ApprovalResultDelivery {
            $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
            $parameters = [
                'now' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'pending' => ApprovalResultDeliveryStatus::Pending->value,
                'running' => ApprovalResultDeliveryStatus::Running->value,
            ];
            while (true) {
                $row = $this->connection->fetchAssociative(
                    'SELECT result.*, delivery.attempt FROM approval_flow_outbox delivery
                     INNER JOIN approval_flow_result result ON result.id = delivery.result_id
                     WHERE (delivery.status = :pending AND delivery.next_attempt_at <= :now)
                        OR (delivery.status = :running AND delivery.lease_until <= :now)
                     ORDER BY delivery.next_attempt_at, delivery.result_id LIMIT 1 FOR UPDATE SKIP LOCKED',
                    $parameters,
                );
                if ($row === false) {
                    return null;
                }
                $attempt = (int) $row['attempt'];
                if ($attempt >= self::MAX_ATTEMPTS) {
                    $this->connection->update('approval_flow_outbox', [
                        'status' => ApprovalResultDeliveryStatus::Failed->value,
                        'lease_until' => null,
                        'updated_at' => $parameters['now'],
                    ], ['result_id' => $row['id']]);
                    continue;
                }

                ++$attempt;
                $leaseUntil = $now->modify('+' . self::LEASE_SECONDS . ' seconds');
                $this->connection->update('approval_flow_outbox', [
                    'status' => ApprovalResultDeliveryStatus::Running->value,
                    'attempt' => $attempt,
                    'lease_until' => $leaseUntil->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'updated_at' => $parameters['now'],
                ], ['result_id' => $row['id']]);
                $result = new ApprovalResult(
                    Uuid::fromBytesToHex($row['id']),
                    Uuid::fromBytesToHex($row['instance_id']),
                    Uuid::fromBytesToHex($row['release_id']),
                    Uuid::fromBytesToHex($row['command_id']),
                    (int) $row['aggregate_version'],
                    ApprovalOutcome::from($row['outcome']),
                    new \DateTimeImmutable($row['finished_at'], new \DateTimeZone('UTC')),
                );

                return new ApprovalResultDelivery($result, $attempt, $leaseUntil);
            }
        });
    }

    public function acknowledge(string $resultId, int $attempt, Context $context): bool
    {
        $this->requireDeliveryContext($context);
        $this->validateAttempt($resultId, $attempt);

        return $this->settle($resultId, $attempt, retry: false);
    }

    public function retry(string $resultId, int $attempt, Context $context): bool
    {
        $this->requireDeliveryContext($context);
        $this->validateAttempt($resultId, $attempt);

        return $this->settle($resultId, $attempt, retry: true);
    }

    private function settle(string $resultId, int $attempt, bool $retry): bool
    {
        return RetryableTransaction::transactional($this->connection, function () use ($resultId, $attempt, $retry): bool {
            $id = Uuid::fromHexToBytes($resultId);
            $this->connection->fetchOne('SELECT result_id FROM approval_flow_outbox WHERE result_id = :id FOR UPDATE', ['id' => $id]);
            // Read the clock after any lock wait, so an expired lease cannot be acknowledged with an earlier time.
            $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
            $status = ApprovalResultDeliveryStatus::Delivered;
            if ($retry) {
                $status = $attempt === self::MAX_ATTEMPTS ? ApprovalResultDeliveryStatus::Failed : ApprovalResultDeliveryStatus::Pending;
            }
            $nextAttemptAt = $retry ? $now->modify('+' . self::RETRY_DELAYS[$attempt - 1] . ' seconds') : $now;

            return $this->connection->executeStatement(
                'UPDATE approval_flow_outbox SET status = :status, lease_until = NULL, next_attempt_at = :next, updated_at = :now
                 WHERE result_id = :id AND status = :running AND attempt = :attempt AND lease_until > :now',
                [
                    'id' => $id, 'attempt' => $attempt, 'status' => $status->value,
                    'running' => ApprovalResultDeliveryStatus::Running->value,
                    'now' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'next' => $nextAttemptAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
            ) === 1;
        });
    }

    private function requireDeliveryContext(Context $context): void
    {
        if (!$context->getSource() instanceof SystemSource) {
            throw ApprovalFlowException::missingPrivileges(['system']);
        }
        // A returned lease or confirmation must already be committed, not held by the caller's transaction.
        if ($this->connection->isTransactionActive()) {
            throw ApprovalFlowException::resultDeliveryTransactionActive();
        }
    }

    private function validateAttempt(string $resultId, int $attempt): void
    {
        if (!Uuid::isValid($resultId) || $attempt < 1 || $attempt > self::MAX_ATTEMPTS) {
            throw ApprovalFlowException::invalidResultDelivery();
        }
    }
}
