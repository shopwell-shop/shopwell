<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Instance;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalNodeEntry;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionCollection;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Operation\ApprovalInstanceCommandResult;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Operation\ApprovalOperationCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceServiceTest
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalStartNodeTest
 */
#[Package('framework')]
class ApprovalInstanceStore
{
    public const SUSPEND = 'suspend';

    public const ENTER_START = 'enter-start';

    private const START_SEQUENCE = 1;

    /**
     * @param EntityRepository<ApprovalInstanceCollection> $instances
     * @param EntityRepository<ApprovalOperationCollection> $operations
     * @param EntityRepository<ApprovalNodeExecutionCollection> $executions
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $instances,
        private readonly EntityRepository $operations,
        private readonly ApprovalInstanceWriteGuard $writeGuard,
        private readonly EntityRepository $executions,
        private readonly ApprovalNodeEntry $nodeEntry,
    ) {
    }

    public function suspend(string $instanceId, string $commandId, int $expectedVersion, string $reason, string $actorJson, Context $context): ApprovalInstanceCommandResult
    {
        $requestHash = Hasher::hash(['operation' => self::SUSPEND, 'expectedVersion' => $expectedVersion, 'reason' => $reason, 'actorJson' => $actorJson], 'sha256');

        return RetryableTransaction::transactional($this->connection, function () use ($instanceId, $commandId, $expectedVersion, $reason, $actorJson, $context, $requestHash): ApprovalInstanceCommandResult {
            $instance = $this->lockInstance($instanceId);
            $receipt = $this->readReceipt($instanceId, $commandId, $requestHash);
            if ($receipt !== null) {
                return $receipt;
            }

            $this->requireRunning($instanceId, $instance['status'], $instance['version'], $expectedVersion, self::SUSPEND);
            $result = new ApprovalInstanceCommandResult($instanceId, $commandId, ApprovalInstanceStatus::Suspended, $expectedVersion + 1);
            $this->saveRunningCommand($result, self::SUSPEND, $requestHash, $reason, $actorJson, $context);

            return $result;
        });
    }

    public function enterStart(string $instanceId, string $commandId, int $expectedVersion, Context $context): ApprovalInstanceCommandResult
    {
        $requestHash = Hasher::hash(['operation' => self::ENTER_START, 'expectedVersion' => $expectedVersion], 'sha256');

        return RetryableTransaction::transactional($this->connection, function () use ($instanceId, $commandId, $expectedVersion, $context, $requestHash): ApprovalInstanceCommandResult {
            $instance = $this->lockInstance($instanceId);
            $receipt = $this->readReceipt($instanceId, $commandId, $requestHash);
            if ($receipt !== null) {
                return $receipt;
            }

            $this->requireRunning($instanceId, $instance['status'], $instance['version'], $expectedVersion, self::ENTER_START);
            $started = $this->connection->fetchOne(
                'SELECT id FROM approval_flow_execution WHERE instance_id = :instance AND `sequence` = :sequence FOR UPDATE',
                ['instance' => Uuid::fromHexToBytes($instanceId), 'sequence' => self::START_SEQUENCE],
            );
            if ($started !== false) {
                throw ApprovalFlowException::startNodeAlreadyEntered($instanceId);
            }

            $transition = $this->nodeEntry->enterStart($instance['releaseId'], $context);
            $this->writeGuard->write($context, function (Context $writeContext) use ($instanceId, $transition): void {
                $this->executions->create([
                    [
                        'id' => Uuid::randomHex(),
                        'instanceId' => $instanceId,
                        'nodeId' => $transition['startNodeId'],
                        'sequence' => self::START_SEQUENCE,
                        'status' => ApprovalNodeExecutionStatus::Completed->value,
                    ],
                    [
                        'id' => Uuid::randomHex(),
                        'instanceId' => $instanceId,
                        'nodeId' => $transition['nextNodeId'],
                        'incomingConnectionId' => $transition['connectionId'],
                        'sequence' => self::START_SEQUENCE + 1,
                        'status' => ApprovalNodeExecutionStatus::Waiting->value,
                    ],
                ], $writeContext);
            });

            $result = new ApprovalInstanceCommandResult($instanceId, $commandId, ApprovalInstanceStatus::Running, $expectedVersion + 1);
            $this->saveRunningCommand($result, self::ENTER_START, $requestHash, 'Enter the published start node.', '{"source":"system"}', $context);

            return $result;
        });
    }

    /**
     * @return array{status: ApprovalInstanceStatus, version: int, releaseId: string}
     */
    private function lockInstance(string $instanceId): array
    {
        // Current locking reads retain their meaning inside an older REPEATABLE READ transaction.
        $instance = $this->connection->fetchAssociative(
            'SELECT status, aggregate_version, release_id FROM approval_flow_instance WHERE id = :id FOR UPDATE',
            ['id' => Uuid::fromHexToBytes($instanceId)],
        );
        if ($instance === false) {
            throw ApprovalFlowException::instanceNotFound($instanceId);
        }

        return [
            'status' => ApprovalInstanceStatus::from($instance['status']),
            'version' => (int) $instance['aggregate_version'],
            'releaseId' => Uuid::fromBytesToHex($instance['release_id']),
        ];
    }

    private function readReceipt(string $instanceId, string $commandId, string $requestHash): ?ApprovalInstanceCommandResult
    {
        $receipt = $this->connection->fetchAssociative(
            'SELECT request_hash, status, aggregate_version FROM approval_flow_operation WHERE instance_id = :instance AND command_id = :command FOR UPDATE',
            ['instance' => Uuid::fromHexToBytes($instanceId), 'command' => Uuid::fromHexToBytes($commandId)],
        );
        if ($receipt === false) {
            return null;
        }
        if (!hash_equals($receipt['request_hash'], $requestHash)) {
            throw ApprovalFlowException::instanceCommandConflict($instanceId, $commandId);
        }

        return new ApprovalInstanceCommandResult($instanceId, $commandId, ApprovalInstanceStatus::from($receipt['status']), (int) $receipt['aggregate_version']);
    }

    private function requireRunning(string $instanceId, ApprovalInstanceStatus $status, int $version, int $expectedVersion, string $operation): void
    {
        if ($version !== $expectedVersion) {
            throw ApprovalFlowException::instanceVersionConflict($instanceId, $expectedVersion, $version);
        }
        if ($status !== ApprovalInstanceStatus::Running) {
            throw ApprovalFlowException::invalidInstanceTransition($instanceId, $status->value, $operation);
        }

        if ($this->connection->getTransactionNestingLevel() > 1) {
            // The DAL reads from the caller's snapshot. A locking read can see an instance that the DAL cannot update yet.
            $visible = $this->connection->fetchOne(
                'SELECT 1 FROM approval_flow_instance WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($instanceId)],
            );
            if ($visible === false) {
                throw ApprovalFlowException::instanceOutsideSnapshot($instanceId);
            }
        }
    }

    private function saveRunningCommand(ApprovalInstanceCommandResult $result, string $operation, string $requestHash, string $reason, string $actorJson, Context $context): void
    {
        $this->writeGuard->write($context, function (Context $writeContext) use ($result): void {
            $this->instances->update([[
                'id' => $result->instanceId,
                'status' => $result->status->value,
                'aggregateVersion' => $result->aggregateVersion,
            ]], $writeContext);
        });
        $this->writeGuard->write($context, function (Context $writeContext) use ($result, $operation, $requestHash, $reason, $actorJson): void {
            $this->operations->create([[
                'instanceId' => $result->instanceId,
                'commandId' => $result->commandId,
                'operation' => $operation,
                'requestHash' => $requestHash,
                'actorJson' => $actorJson,
                'reason' => $reason,
                'previousStatus' => ApprovalInstanceStatus::Running->value,
                'status' => $result->status->value,
                'previousVersion' => $result->aggregateVersion - 1,
                'aggregateVersion' => $result->aggregateVersion,
            ]], $writeContext);
        });
    }
}
