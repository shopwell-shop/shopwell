<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * Current reads for cancellation inside a business transaction that may already have a snapshot.
 * All methods run inside WorkflowTransactionRunner, with the instance locked before task locks.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Execution\ApprovalWorkflowRuntimeTest
 */
#[Package('framework')]
final readonly class WorkflowCancellationStore
{
    public function __construct(private Connection $connection, private ClockInterface $clock)
    {
    }

    /**
     * @return array{initiatorId: string, stateId: string}
     */
    public function lockInstance(string $instanceId): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT initiator_id, state_id FROM workflow_instance WHERE id = :id FOR UPDATE',
            ['id' => Uuid::fromHexToBytes($instanceId)],
        );
        if ($row === false) {
            throw WorkflowExecutionException::instanceNotFound($instanceId);
        }

        return ['initiatorId' => Uuid::fromBytesToHex($row['initiator_id']), 'stateId' => Uuid::fromBytesToHex($row['state_id'])];
    }

    /**
     * @return list<string>
     */
    public function lockPendingTasks(string $instanceId, string $todoStateId): array
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT id FROM workflow_task WHERE instance_id = :instance AND state_id = :state ORDER BY id FOR UPDATE',
            ['instance' => Uuid::fromHexToBytes($instanceId), 'state' => Uuid::fromHexToBytes($todoStateId)],
        );

        return array_map(Uuid::fromBytesToHex(...), $ids);
    }

    /**
     * Record cancellation timestamps before the normal state-machine transitions.
     * These real writes also make the current locked rows visible to the registry's DAL reads
     * under REPEATABLE READ, including tasks created after the caller's original snapshot.
     * The outer transaction rolls the timestamps back if any transition or business write fails.
     *
     * @param list<string> $taskIds Only currently pending tasks returned by lockPendingTasks().
     */
    public function beginCancellation(string $instanceId, array $taskIds): void
    {
        $now = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->connection->update('workflow_instance', ['ended_at' => $now], ['id' => Uuid::fromHexToBytes($instanceId)]);
        if ($taskIds !== []) {
            $this->connection->executeStatement(
                'UPDATE workflow_task SET completed_at = :now WHERE id IN (:ids)',
                ['now' => $now, 'ids' => array_map(Uuid::fromHexToBytes(...), $taskIds)],
                ['ids' => ArrayParameterType::BINARY],
            );
        }
    }
}
