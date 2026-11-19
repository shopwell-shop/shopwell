<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Instance;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
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
 */
#[Package('framework')]
class ApprovalInstanceStore
{
    public const SUSPEND = 'suspend';

    /**
     * @param EntityRepository<ApprovalInstanceCollection> $instances
     * @param EntityRepository<ApprovalOperationCollection> $operations
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $instances,
        private readonly EntityRepository $operations,
        private readonly ApprovalInstanceWriteGuard $writeGuard,
    ) {
    }

    public function suspend(string $instanceId, string $commandId, int $expectedVersion, string $reason, string $actorJson, Context $context): ApprovalInstanceCommandResult
    {
        $requestHash = Hasher::hash(['operation' => self::SUSPEND, 'expectedVersion' => $expectedVersion, 'reason' => $reason, 'actorJson' => $actorJson], 'sha256');

        return RetryableTransaction::transactional($this->connection, function () use ($instanceId, $commandId, $expectedVersion, $reason, $actorJson, $context, $requestHash): ApprovalInstanceCommandResult {
            // Current locking reads retain their meaning inside an older REPEATABLE READ transaction.
            $instance = $this->connection->fetchAssociative(
                'SELECT status, aggregate_version FROM approval_flow_instance WHERE id = :id FOR UPDATE',
                ['id' => Uuid::fromHexToBytes($instanceId)],
            );
            if ($instance === false) {
                throw ApprovalFlowException::instanceNotFound($instanceId);
            }
            $receipt = $this->connection->fetchAssociative(
                'SELECT request_hash, status, aggregate_version FROM approval_flow_operation WHERE instance_id = :instance AND command_id = :command FOR UPDATE',
                ['instance' => Uuid::fromHexToBytes($instanceId), 'command' => Uuid::fromHexToBytes($commandId)],
            );
            if ($receipt !== false) {
                if (!hash_equals($receipt['request_hash'], $requestHash)) {
                    throw ApprovalFlowException::instanceCommandConflict($instanceId, $commandId);
                }

                return new ApprovalInstanceCommandResult($instanceId, $commandId, ApprovalInstanceStatus::from($receipt['status']), (int) $receipt['aggregate_version']);
            }

            $version = (int) $instance['aggregate_version'];
            if ($version !== $expectedVersion) {
                throw ApprovalFlowException::instanceVersionConflict($instanceId, $expectedVersion, $version);
            }
            $status = ApprovalInstanceStatus::from($instance['status']);
            if ($status !== ApprovalInstanceStatus::Running) {
                throw ApprovalFlowException::invalidInstanceTransition($instanceId, $status->value, self::SUSPEND);
            }
            $nextVersion = $version + 1;
            $nextStatus = ApprovalInstanceStatus::Suspended;

            $this->writeGuard->write($context, function (Context $writeContext) use ($instanceId, $nextVersion, $nextStatus): void {
                $this->instances->update([[
                    'id' => $instanceId,
                    'status' => $nextStatus->value,
                    'aggregateVersion' => $nextVersion,
                ]], $writeContext);
            });
            $this->writeGuard->write($context, function (Context $writeContext) use ($instanceId, $commandId, $requestHash, $actorJson, $reason, $status, $version, $nextVersion, $nextStatus): void {
                $this->operations->create([[
                    'instanceId' => $instanceId,
                    'commandId' => $commandId,
                    'operation' => self::SUSPEND,
                    'requestHash' => $requestHash,
                    'actorJson' => $actorJson,
                    'reason' => $reason,
                    'previousStatus' => $status->value,
                    'status' => $nextStatus->value,
                    'previousVersion' => $version,
                    'aggregateVersion' => $nextVersion,
                ]], $writeContext);
            });

            return new ApprovalInstanceCommandResult($instanceId, $commandId, $nextStatus, $nextVersion);
        });
    }
}
