<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Result;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceWriteGuard;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * Appends a completion and its delivery under the instance command's lock and transaction.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Result\ApprovalResultOutboxTest
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalCompletionTest
 */
#[Package('framework')]
class ApprovalResultStore
{
    /**
     * @param EntityRepository<ApprovalResultCollection> $results
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $results,
        private readonly ApprovalInstanceWriteGuard $writeGuard,
        private readonly ClockInterface $clock,
    ) {
    }

    public function record(string $instanceId, string $releaseId, string $commandId, int $aggregateVersion, ApprovalOutcome $outcome, Context $context): void
    {
        if (!$this->connection->isTransactionActive()) {
            throw ApprovalFlowException::resultTransactionRequired();
        }
        $id = Uuid::randomHex();
        $finishedAt = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $this->writeGuard->write($context, function (Context $writeContext) use ($id, $instanceId, $releaseId, $commandId, $aggregateVersion, $outcome, $finishedAt): void {
            $this->results->create([[
                'id' => $id,
                'instanceId' => $instanceId,
                'releaseId' => $releaseId,
                'commandId' => $commandId,
                'aggregateVersion' => $aggregateVersion,
                'outcome' => $outcome->value,
                'finishedAt' => $finishedAt,
            ]], $writeContext);
        });
        $this->connection->insert('approval_flow_outbox', [
            'result_id' => Uuid::fromHexToBytes($id),
            'status' => ApprovalResultDeliveryStatus::Pending->value,
            'attempt' => 0,
            'next_attempt_at' => $finishedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'created_at' => $finishedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
