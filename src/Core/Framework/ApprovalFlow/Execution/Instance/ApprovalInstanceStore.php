<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Instance;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalNodeEntry;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\RequestApprovalEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionCollection;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Operation\ApprovalInstanceCommandResult;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Operation\ApprovalOperationCollection;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalAssigneeResolver;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskCollection;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskDecisionStore;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskStatus;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalPolicy;
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
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalNodeProgressionTest
 */
#[Package('framework')]
class ApprovalInstanceStore
{
    public const SUSPEND = 'suspend';

    public const ENTER_START = 'enter-start';

    public const ENTER_APPROVAL = 'enter-approval';

    public const DECIDE = 'decide';

    private const START_SEQUENCE = 1;

    private const SYSTEM_ACTOR_JSON = '{"source":"system"}';

    /**
     * @param EntityRepository<ApprovalInstanceCollection> $instances
     * @param EntityRepository<ApprovalOperationCollection> $operations
     * @param EntityRepository<ApprovalNodeExecutionCollection> $executions
     * @param EntityRepository<ApprovalTaskCollection> $tasks
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $instances,
        private readonly EntityRepository $operations,
        private readonly ApprovalInstanceWriteGuard $writeGuard,
        private readonly EntityRepository $executions,
        private readonly ApprovalNodeEntry $nodeEntry,
        private readonly EntityRepository $tasks,
        private readonly ApprovalAssigneeResolver $assigneeResolver,
        private readonly ApprovalTaskDecisionStore $decisions,
    ) {
    }

    public function suspend(string $instanceId, string $commandId, int $expectedVersion, string $reason, string $actorJson, Context $context): ApprovalInstanceCommandResult
    {
        $requestHash = Hasher::hash(['operation' => self::SUSPEND, 'expectedVersion' => $expectedVersion, 'reason' => $reason, 'actorJson' => $actorJson], 'sha256');

        return $this->runCommand(
            $instanceId,
            $commandId,
            $expectedVersion,
            self::SUSPEND,
            $requestHash,
            $reason,
            $actorJson,
            $context,
            static fn (): ApprovalInstanceStatus => ApprovalInstanceStatus::Suspended,
        );
    }

    public function enterStart(string $instanceId, string $commandId, int $expectedVersion, Context $context): ApprovalInstanceCommandResult
    {
        $requestHash = Hasher::hash(['operation' => self::ENTER_START, 'expectedVersion' => $expectedVersion], 'sha256');

        return $this->runCommand(
            $instanceId,
            $commandId,
            $expectedVersion,
            self::ENTER_START,
            $requestHash,
            'Enter the published start node.',
            self::SYSTEM_ACTOR_JSON,
            $context,
            function (string $releaseId) use ($instanceId, $context): ApprovalInstanceStatus {
                $started = $this->connection->fetchOne(
                    'SELECT id FROM approval_flow_execution WHERE instance_id = :instance AND `sequence` = :sequence FOR UPDATE',
                    ['instance' => Uuid::fromHexToBytes($instanceId), 'sequence' => self::START_SEQUENCE],
                );
                if ($started !== false) {
                    throw ApprovalFlowException::startNodeAlreadyEntered($instanceId);
                }

                $transition = $this->nodeEntry->enterStart($releaseId, $context);
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

                return ApprovalInstanceStatus::Running;
            },
        );
    }

    public function enterApproval(string $instanceId, string $executionId, string $commandId, int $expectedVersion, Context $context): ApprovalInstanceCommandResult
    {
        $requestHash = Hasher::hash(['operation' => self::ENTER_APPROVAL, 'expectedVersion' => $expectedVersion, 'executionId' => $executionId], 'sha256');

        return $this->runCommand(
            $instanceId,
            $commandId,
            $expectedVersion,
            self::ENTER_APPROVAL,
            $requestHash,
            'Create tasks for node execution ' . $executionId . '.',
            self::SYSTEM_ACTOR_JSON,
            $context,
            function (string $releaseId) use ($instanceId, $executionId, $context): ApprovalInstanceStatus {
                $nodeId = $this->waitingNode($instanceId, $executionId);
                $effect = $this->nodeEntry->enter($releaseId, $nodeId, $context);
                if (!$effect instanceof RequestApprovalEffect) {
                    throw ApprovalFlowException::invalidApprovalEntry($executionId, 'The node did not request approval.');
                }
                $assignees = $this->assigneeResolver->resolveAssignees($effect->configuration);
                $this->createApprovalTasks($executionId, $assignees, ApprovalPolicy::from($effect->configuration->policy), $context);

                return ApprovalInstanceStatus::Running;
            },
        );
    }

    public function decide(string $instanceId, string $taskId, string $commandId, int $expectedVersion, ApprovalOutcome $outcome, string $reason, string $userId, string $actorJson, Context $context): ApprovalInstanceCommandResult
    {
        $requestHash = Hasher::hash([
            'operation' => self::DECIDE,
            'taskId' => $taskId,
            'expectedVersion' => $expectedVersion,
            'outcome' => $outcome->value,
            'reason' => $reason,
            'actorJson' => $actorJson,
        ], 'sha256');

        return $this->runCommand(
            $instanceId,
            $commandId,
            $expectedVersion,
            self::DECIDE,
            $requestHash,
            $reason,
            $actorJson,
            $context,
            function () use ($instanceId, $taskId, $commandId, $outcome, $context): ApprovalInstanceStatus {
                $this->decisions->decide($instanceId, $taskId, $commandId, $outcome, $context);

                return ApprovalInstanceStatus::Running;
            },
            fn () => $this->decisions->requireAssignee($instanceId, $taskId, $userId),
        );
    }

    /**
     * @param \Closure(string): ApprovalInstanceStatus $apply
     * @param (\Closure(): void)|null $authorize
     */
    private function runCommand(
        string $instanceId,
        string $commandId,
        int $expectedVersion,
        string $operation,
        string $requestHash,
        string $reason,
        string $actorJson,
        Context $context,
        \Closure $apply,
        ?\Closure $authorize = null,
    ): ApprovalInstanceCommandResult {
        return RetryableTransaction::transactional($this->connection, function () use ($instanceId, $commandId, $expectedVersion, $operation, $requestHash, $reason, $actorJson, $context, $apply, $authorize): ApprovalInstanceCommandResult {
            $instance = $this->lockInstance($instanceId);
            if ($authorize !== null) {
                $authorize();
            }
            $receipt = $this->readReceipt($instanceId, $commandId, $requestHash);
            if ($receipt !== null) {
                return $receipt;
            }

            $this->requireRunning($instanceId, $instance['status'], $instance['version'], $expectedVersion, $operation);
            $status = $apply($instance['releaseId']);
            $result = new ApprovalInstanceCommandResult($instanceId, $commandId, $status, $expectedVersion + 1);
            $this->saveRunningCommand($result, $operation, $requestHash, $reason, $actorJson, $context);

            return $result;
        });
    }

    private function waitingNode(string $instanceId, string $executionId): string
    {
        $parameters = ['execution' => Uuid::fromHexToBytes($executionId), 'instance' => Uuid::fromHexToBytes($instanceId)];
        $execution = $this->connection->fetchAssociative(
            'SELECT node_id, status FROM approval_flow_execution WHERE id = :execution AND instance_id = :instance FOR UPDATE',
            $parameters,
        );
        if ($execution === false) {
            throw ApprovalFlowException::executionNotFound($executionId, $instanceId);
        }

        $status = ApprovalNodeExecutionStatus::from($execution['status']);
        if ($status !== ApprovalNodeExecutionStatus::Waiting) {
            throw ApprovalFlowException::invalidApprovalEntry($executionId, 'The current status is ' . $status->value . '.');
        }

        if ($this->connection->getTransactionNestingLevel() > 1 && $this->connection->fetchOne(
            'SELECT 1 FROM approval_flow_execution WHERE id = :execution AND instance_id = :instance',
            $parameters,
        ) === false) {
            throw ApprovalFlowException::executionOutsideSnapshot($executionId);
        }

        return $execution['node_id'];
    }

    /**
     * @param non-empty-list<string> $assignees
     */
    private function createApprovalTasks(string $executionId, array $assignees, ApprovalPolicy $policy, Context $context): void
    {
        $tasks = [];
        foreach ($assignees as $assigneeId) {
            $tasks[] = [
                'id' => Uuid::randomHex(),
                'executionId' => $executionId,
                'assigneeId' => $assigneeId,
                'status' => ApprovalTaskStatus::Pending->value,
            ];
        }

        $this->writeGuard->write($context, function (Context $writeContext) use ($tasks): void {
            $this->tasks->create($tasks, $writeContext);
        });
        $this->writeGuard->write($context, function (Context $writeContext) use ($executionId, $policy): void {
            $this->executions->update([[
                'id' => $executionId,
                'status' => ApprovalNodeExecutionStatus::AwaitingApproval->value,
                'approvalPolicy' => $policy->value,
            ]], $writeContext);
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
