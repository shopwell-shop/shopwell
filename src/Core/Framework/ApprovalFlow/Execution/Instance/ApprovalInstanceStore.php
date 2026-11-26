<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Instance;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Command\ApprovalExecutionCommandContext;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Command\ApprovalExecutionCommandHandler;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Operation\ApprovalInstanceCommandResult;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Operation\ApprovalOperationCollection;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskDecisionStore;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultStore;
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
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalCompletionTest
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Request\ApprovalRequestStartTest
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalSerialProgressionTest
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalProgressionRunnerTest
 */
#[Package('framework')]
class ApprovalInstanceStore
{
    public const PROGRESS = 'progress';

    public const START = 'start';

    public const SUSPEND = 'suspend';

    public const WITHDRAW = 'withdraw';

    public const ENTER_START = 'enter-start';

    public const ENTER_APPROVAL = 'enter-approval';

    public const DECIDE = 'decide';

    public const ADVANCE_APPROVAL = 'advance-approval';

    public const ENTER_END = 'enter-end';

    /**
     * @var array<string, true>
     */
    private array $activeCommands = [];

    /**
     * @param EntityRepository<ApprovalInstanceCollection> $instances
     * @param EntityRepository<ApprovalOperationCollection> $operations
     * @param array<string, ApprovalExecutionCommandHandler> $executionCommands
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $instances,
        private readonly EntityRepository $operations,
        private readonly ApprovalInstanceWriteGuard $writeGuard,
        private readonly array $executionCommands,
        private readonly ApprovalTaskDecisionStore $decisions,
        private readonly ApprovalResultStore $results,
    ) {
    }

    /**
     * Candidate discovery only; the command rechecks all state under the instance lock.
     *
     * @return list<array{instanceId: string, executionId: string, version: int}>
     */
    public function pendingProgressions(?string $after, int $limit): array
    {
        return $this->findProgressions($after, $limit);
    }

    /**
     * @return array{instanceId: string, executionId: string, version: int}|null
     */
    public function nextProgression(string $instanceId): ?array
    {
        return $this->findProgressions(null, 1, $instanceId)[0] ?? null;
    }

    public function start(string $instanceId, string $commandId, int $expectedVersion, string $actorJson, Context $context): ApprovalInstanceCommandResult
    {
        $requestHash = Hasher::hash(['operation' => self::START, 'expectedVersion' => $expectedVersion, 'actorJson' => $actorJson], 'sha256');

        return $this->runCommand(
            $instanceId,
            $commandId,
            $expectedVersion,
            self::START,
            $requestHash,
            'Start the admitted request and create its initial approval tasks.',
            $actorJson,
            $context,
            fn (string $releaseId): ApprovalInstanceStatus|ApprovalOutcome => $this->executionCommands[self::START]->execute(
                new ApprovalExecutionCommandContext($instanceId, $releaseId, $commandId, $context),
            ),
            from: ApprovalInstanceStatus::Draft,
        );
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

    public function withdraw(string $instanceId, string $reason, string $actorJson, Context $context): ApprovalInstanceCommandResult
    {
        // Withdrawal applies to the current running instance and has one receipt per instance.
        $commandId = Uuid::fromStringToHex(self::WITHDRAW . ':' . $instanceId);
        $requestHash = Hasher::hash(['operation' => self::WITHDRAW, 'reason' => $reason, 'actorJson' => $actorJson], 'sha256');

        return $this->runCommand(
            $instanceId,
            $commandId,
            null,
            self::WITHDRAW,
            $requestHash,
            $reason,
            $actorJson,
            $context,
            fn (string $releaseId): ApprovalInstanceStatus|ApprovalOutcome => $this->executionCommands[self::WITHDRAW]->execute(
                new ApprovalExecutionCommandContext($instanceId, $releaseId, $commandId, $context),
            ),
        );
    }

    public function enterStart(string $instanceId, string $commandId, int $expectedVersion, Context $context): ApprovalInstanceCommandResult
    {
        $actorJson = $this->actorJson($context);
        $requestHash = Hasher::hash(['operation' => self::ENTER_START, 'expectedVersion' => $expectedVersion, 'actorJson' => $actorJson], 'sha256');

        return $this->runCommand(
            $instanceId,
            $commandId,
            $expectedVersion,
            self::ENTER_START,
            $requestHash,
            'Enter the published start node.',
            $actorJson,
            $context,
            fn (string $releaseId): ApprovalInstanceStatus|ApprovalOutcome => $this->executionCommands[self::ENTER_START]->execute(
                new ApprovalExecutionCommandContext($instanceId, $releaseId, $commandId, $context),
            ),
        );
    }

    public function enterApproval(string $instanceId, string $executionId, string $commandId, int $expectedVersion, Context $context): ApprovalInstanceCommandResult
    {
        $actorJson = $this->actorJson($context);
        $requestHash = Hasher::hash(['operation' => self::ENTER_APPROVAL, 'expectedVersion' => $expectedVersion, 'executionId' => $executionId, 'actorJson' => $actorJson], 'sha256');

        return $this->runCommand(
            $instanceId,
            $commandId,
            $expectedVersion,
            self::ENTER_APPROVAL,
            $requestHash,
            'Create tasks for node execution ' . $executionId . '.',
            $actorJson,
            $context,
            fn (string $releaseId): ApprovalInstanceStatus|ApprovalOutcome => $this->executionCommands[self::ENTER_APPROVAL]->execute(
                new ApprovalExecutionCommandContext($instanceId, $releaseId, $commandId, $context, $executionId),
            ),
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

    public function advanceApproval(string $instanceId, string $executionId, string $commandId, int $expectedVersion, Context $context): ApprovalInstanceCommandResult
    {
        $actorJson = $this->actorJson($context);
        $requestHash = Hasher::hash(['operation' => self::ADVANCE_APPROVAL, 'executionId' => $executionId, 'expectedVersion' => $expectedVersion, 'actorJson' => $actorJson], 'sha256');

        return $this->runCommand(
            $instanceId,
            $commandId,
            $expectedVersion,
            self::ADVANCE_APPROVAL,
            $requestHash,
            'Advance completed approval ' . $executionId . '.',
            $actorJson,
            $context,
            fn (string $releaseId): ApprovalInstanceStatus|ApprovalOutcome => $this->executionCommands[self::ADVANCE_APPROVAL]->execute(
                new ApprovalExecutionCommandContext($instanceId, $releaseId, $commandId, $context, $executionId),
            ),
        );
    }

    public function progress(string $instanceId, string $executionId, string $commandId, int $expectedVersion, Context $context): ApprovalInstanceCommandResult
    {
        $actorJson = $this->actorJson($context);
        $requestHash = Hasher::hash(['operation' => self::PROGRESS, 'executionId' => $executionId, 'expectedVersion' => $expectedVersion, 'actorJson' => $actorJson], 'sha256');

        return $this->runCommand(
            $instanceId,
            $commandId,
            $expectedVersion,
            self::PROGRESS,
            $requestHash,
            'Progress completed approval ' . $executionId . ' to its next wait or end boundary.',
            $actorJson,
            $context,
            fn (string $releaseId): ApprovalInstanceStatus|ApprovalOutcome => $this->executionCommands[self::PROGRESS]->execute(
                new ApprovalExecutionCommandContext($instanceId, $releaseId, $commandId, $context, $executionId),
            ),
        );
    }

    public function enterEnd(string $instanceId, string $executionId, string $commandId, int $expectedVersion, Context $context): ApprovalInstanceCommandResult
    {
        $actorJson = $this->actorJson($context);
        $requestHash = Hasher::hash(['operation' => self::ENTER_END, 'executionId' => $executionId, 'expectedVersion' => $expectedVersion, 'actorJson' => $actorJson], 'sha256');

        return $this->runCommand(
            $instanceId,
            $commandId,
            $expectedVersion,
            self::ENTER_END,
            $requestHash,
            'Enter the published end at execution ' . $executionId . '.',
            $actorJson,
            $context,
            fn (string $releaseId): ApprovalInstanceStatus|ApprovalOutcome => $this->executionCommands[self::ENTER_END]->execute(
                new ApprovalExecutionCommandContext($instanceId, $releaseId, $commandId, $context, $executionId),
            ),
        );
    }

    /**
     * @return list<array{instanceId: string, executionId: string, version: int}>
     */
    private function findProgressions(?string $after, int $limit, ?string $instanceId = null): array
    {
        $parameters = ['running' => ApprovalInstanceStatus::Running->value, 'completed' => ApprovalNodeExecutionStatus::Completed->value, 'limit' => $limit];
        $cursor = '';
        if ($after !== null) {
            $cursor = ' AND i.id > :after';
            $parameters['after'] = Uuid::fromHexToBytes($after);
        }
        if ($instanceId !== null) {
            $cursor .= ' AND i.id = :instance';
            $parameters['instance'] = Uuid::fromHexToBytes($instanceId);
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT i.id, i.aggregate_version, e.id AS execution_id
             FROM approval_flow_instance i INNER JOIN approval_flow_execution e ON e.instance_id = i.id
             WHERE i.status = :running AND e.status = :completed AND e.approval_policy IS NOT NULL AND e.outcome IS NOT NULL
               AND e.advanced = 0
               AND NOT EXISTS (SELECT 1 FROM approval_flow_execution earlier WHERE earlier.instance_id = i.id AND earlier.status = :completed AND earlier.approval_policy IS NOT NULL AND earlier.outcome IS NOT NULL AND earlier.advanced = 0 AND earlier.`sequence` < e.`sequence`)' .
               $cursor . '
             ORDER BY i.id LIMIT :limit',
            $parameters,
            ['limit' => ParameterType::INTEGER],
        );
        $pending = [];
        foreach ($rows as $row) {
            $pending[] = ['instanceId' => Uuid::fromBytesToHex($row['id']), 'executionId' => Uuid::fromBytesToHex($row['execution_id']), 'version' => (int) $row['aggregate_version']];
        }

        return $pending;
    }

    /**
     * An outcome completes the instance; a status keeps the command's nonterminal transition explicit.
     *
     * @param \Closure(string): (ApprovalInstanceStatus|ApprovalOutcome) $apply
     * @param (\Closure(): void)|null $authorize
     */
    private function runCommand(
        string $instanceId,
        string $commandId,
        ?int $expectedVersion,
        string $operation,
        string $requestHash,
        string $reason,
        string $actorJson,
        Context $context,
        \Closure $apply,
        ?\Closure $authorize = null,
        ApprovalInstanceStatus $from = ApprovalInstanceStatus::Running,
    ): ApprovalInstanceCommandResult {
        if (isset($this->activeCommands[$instanceId])) {
            throw ApprovalFlowException::instanceCommandInProgress($instanceId);
        }
        $this->activeCommands[$instanceId] = true;
        try {
            return RetryableTransaction::transactional($this->connection, function () use ($instanceId, $commandId, $expectedVersion, $operation, $requestHash, $reason, $actorJson, $context, $apply, $authorize, $from): ApprovalInstanceCommandResult {
                $instance = $this->lockInstance($instanceId);
                if ($authorize !== null) {
                    $authorize();
                }
                $receipt = $this->readReceipt($instanceId, $commandId, $requestHash);
                if ($receipt !== null) {
                    return $receipt;
                }

                $this->requireState($instanceId, $instance['status'], $instance['version'], $expectedVersion, $operation, $from);
                $transition = $apply($instance['releaseId']);
                $outcome = $transition instanceof ApprovalOutcome ? $transition : null;
                $status = $transition instanceof ApprovalInstanceStatus ? $transition : ApprovalInstanceStatus::Completed;
                $result = new ApprovalInstanceCommandResult($instanceId, $commandId, $status, $instance['version'] + 1, $outcome);
                $this->saveCommand($result, $operation, $requestHash, $reason, $actorJson, $instance['status'], $context);
                if ($outcome !== null) {
                    $this->results->record($instanceId, $instance['releaseId'], $commandId, $result->aggregateVersion, $outcome, $context);
                }

                return $result;
            });
        } finally {
            unset($this->activeCommands[$instanceId]);
        }
    }

    private function actorJson(Context $context): string
    {
        $source = $context->getSource();
        $actor = match (true) {
            $source instanceof AdminApiSource => ['source' => 'admin-api', 'userId' => $source->getUserId(), 'integrationId' => $source->getIntegrationId()],
            $source instanceof SystemSource => ['source' => 'system'],
            default => ['source' => $source::class],
        };

        return json_encode($actor, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
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
            'SELECT request_hash, status, aggregate_version, outcome FROM approval_flow_operation WHERE instance_id = :instance AND command_id = :command FOR UPDATE',
            ['instance' => Uuid::fromHexToBytes($instanceId), 'command' => Uuid::fromHexToBytes($commandId)],
        );
        if ($receipt === false) {
            return null;
        }
        if (!hash_equals($receipt['request_hash'], $requestHash)) {
            throw ApprovalFlowException::instanceCommandConflict($instanceId, $commandId);
        }

        $outcome = $receipt['outcome'] === null ? null : ApprovalOutcome::from($receipt['outcome']);

        return new ApprovalInstanceCommandResult($instanceId, $commandId, ApprovalInstanceStatus::from($receipt['status']), (int) $receipt['aggregate_version'], $outcome);
    }

    private function requireState(string $instanceId, ApprovalInstanceStatus $status, int $version, ?int $expectedVersion, string $operation, ApprovalInstanceStatus $from): void
    {
        if ($expectedVersion !== null && $version !== $expectedVersion) {
            throw ApprovalFlowException::instanceVersionConflict($instanceId, $expectedVersion, $version);
        }
        if ($status !== $from) {
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

    private function saveCommand(ApprovalInstanceCommandResult $result, string $operation, string $requestHash, string $reason, string $actorJson, ApprovalInstanceStatus $previousStatus, Context $context): void
    {
        $this->writeGuard->write($context, function (Context $writeContext) use ($result): void {
            $this->instances->update([[
                'id' => $result->instanceId,
                'status' => $result->status->value,
                'aggregateVersion' => $result->aggregateVersion,
                'outcome' => $result->outcome?->value,
            ]], $writeContext);
        });
        $this->writeGuard->write($context, function (Context $writeContext) use ($result, $operation, $requestHash, $reason, $actorJson, $previousStatus): void {
            $this->operations->create([[
                'instanceId' => $result->instanceId,
                'commandId' => $result->commandId,
                'operation' => $operation,
                'requestHash' => $requestHash,
                'actorJson' => $actorJson,
                'reason' => $reason,
                'previousStatus' => $previousStatus->value,
                'status' => $result->status->value,
                'previousVersion' => $result->aggregateVersion - 1,
                'aggregateVersion' => $result->aggregateVersion,
                'outcome' => $result->outcome?->value,
            ]], $writeContext);
        });
    }
}
