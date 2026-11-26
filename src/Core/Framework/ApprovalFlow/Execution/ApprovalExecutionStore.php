<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Branch\ApprovalExecutionBranchCollection;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Branch\ApprovalExecutionBranchStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Event\ApprovalReturnTargetsEvent;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceWriteGuard;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionCollection;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\ActivateRoutes;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\ApprovalExecutionPlan;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\CloseScope;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\CompleteExecution;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\CompleteInstance;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\CreateApprovalTasks;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\MarkExecutionAdvanced;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\OpenScope;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\RejectInstance;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\ReturnToApproval;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\WaitForArrival;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Scope\ApprovalExecutionScopeCollection;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Scope\ApprovalExecutionScopeStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Scope\ApprovalScopeSnapshot;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskCollection;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskCompletionReason;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskStatus;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * Owns locked execution, scope, branch and task facts under the instance command transaction.
 * It does not read the flow graph, invoke node executors or schedule ready work.
 *
 * @phpstan-import-type Target from ApprovalReturnTargetsEvent
 *
 * @phpstan-type Scope array{id: string, parentId: ?string, generation: int, joinNodeId: string, status: ApprovalExecutionScopeStatus}
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalParallelExecutionTest
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalSerialProgressionTest
 */
#[Package('framework')]
class ApprovalExecutionStore
{
    /**
     * @param EntityRepository<ApprovalNodeExecutionCollection> $executions
     * @param EntityRepository<ApprovalExecutionScopeCollection> $scopes
     * @param EntityRepository<ApprovalExecutionBranchCollection> $branches
     * @param EntityRepository<ApprovalTaskCollection> $tasks
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $executions,
        private readonly EntityRepository $scopes,
        private readonly EntityRepository $branches,
        private readonly EntityRepository $tasks,
        private readonly ApprovalInstanceWriteGuard $writeGuard,
    ) {
    }

    /**
     * Applies a complete prepared step under the instance command's lock and transaction.
     * A plan is bound to the state it was computed from; stale plans fail before their first write.
     */
    public function apply(string $instanceId, ApprovalExecutionPlan $plan, string $commandId, Context $context): ?ApprovalOutcome
    {
        $execution = $this->execution($instanceId, $plan->execution->id);
        if (get_object_vars($execution) !== get_object_vars($plan->execution)) {
            throw ApprovalFlowException::invalidNodeProgression($execution->id, 'The execution changed after its plan was prepared.');
        }
        $this->requireActive($instanceId, $execution);
        foreach ($plan->steps as $step) {
            if ($step instanceof CloseScope) {
                $this->closeScope($instanceId, $step->scopeId, $commandId, $context);
            } elseif ($step instanceof CompleteExecution) {
                $this->completeExecution($execution->id, $context);
            } elseif ($step instanceof MarkExecutionAdvanced) {
                $this->markAdvanced($execution->id, $context);
            } elseif ($step instanceof ActivateRoutes) {
                $this->activateRoutes($instanceId, $execution, $step->routes, $context);
            } elseif ($step instanceof OpenScope) {
                $this->openScope($instanceId, $execution, $step->joinNodeId, $step->routes, $context);
            } elseif ($step instanceof CreateApprovalTasks) {
                $this->requestApproval($execution->id, $step, $context);
            } elseif ($step instanceof WaitForArrival) {
                $this->waitForArrival($execution->id, $context);
            } elseif ($step instanceof ReturnToApproval) {
                $this->returnToApproval($instanceId, $execution, $step, $commandId, $context);
            } elseif ($step instanceof RejectInstance) {
                $this->reject($instanceId, $commandId, $context);

                return ApprovalOutcome::Rejected;
            } elseif ($step instanceof CompleteInstance) {
                return $this->complete($instanceId, $execution, $step->outcome, $context);
            } else {
                throw ApprovalFlowException::invalidExecutionPlan('Unsupported execution change ' . $step::class . '.');
            }
        }

        return null;
    }

    public function hasTasks(string $instanceId): bool
    {
        return $this->connection->fetchOne(
            'SELECT task.id FROM approval_flow_task task INNER JOIN approval_flow_execution e ON e.id = task.execution_id WHERE e.instance_id = :instance LIMIT 1 FOR UPDATE',
            ['instance' => Uuid::fromHexToBytes($instanceId)],
        ) !== false;
    }

    public function nextReady(string $instanceId): ?ApprovalExecutionState
    {
        $id = $this->connection->fetchOne(
            'SELECT id FROM approval_flow_execution WHERE instance_id = :instance AND status = :ready AND advanced = 0 ORDER BY `sequence` LIMIT 1 FOR UPDATE',
            ['instance' => Uuid::fromHexToBytes($instanceId), 'ready' => ApprovalNodeExecutionStatus::Waiting->value],
        );

        return $id === false ? null : $this->execution($instanceId, Uuid::fromBytesToHex($id));
    }

    public function completedApproval(string $instanceId, string $executionId): ApprovalExecutionState
    {
        $execution = $this->execution($instanceId, $executionId);
        if ($execution->status !== ApprovalNodeExecutionStatus::Completed || $execution->approvalPolicy === null || $execution->outcome === null || $execution->advanced) {
            throw ApprovalFlowException::invalidNodeProgression($executionId, 'An unadvanced completed approval group and its frozen outcome are required.');
        }
        $this->requireActive($instanceId, $execution);
        $unsettledTask = $this->connection->fetchOne(
            'SELECT id FROM approval_flow_task WHERE execution_id = :execution AND status = :pending LIMIT 1 FOR UPDATE',
            ['execution' => Uuid::fromHexToBytes($executionId), 'pending' => ApprovalTaskStatus::Pending->value],
        );
        if ($unsettledTask !== false) {
            throw ApprovalFlowException::invalidNodeProgression($executionId, 'Approval tasks are still pending.');
        }

        return $execution;
    }

    public function hasRejectedApproval(string $instanceId): bool
    {
        return $this->connection->fetchOne(
            'SELECT id FROM approval_flow_execution WHERE instance_id = :instance AND outcome = :rejected AND advanced = 0 LIMIT 1 FOR UPDATE',
            ['instance' => Uuid::fromHexToBytes($instanceId), 'rejected' => ApprovalOutcome::Rejected->value],
        ) !== false;
    }

    /**
     * @return list<string>
     */
    public function enclosingScopeBoundaries(string $instanceId, ?string $scopeId): array
    {
        $boundaries = [];
        while ($scopeId !== null) {
            $scope = $this->scope($instanceId, $scopeId);
            $boundaries[] = $scope['joinNodeId'];
            $scopeId = $scope['parentId'];
        }

        return $boundaries;
    }

    public function requireActive(string $instanceId, ApprovalExecutionState $execution): void
    {
        $scopeId = $execution->scopeId;
        if ($scopeId !== null) {
            $scope = $this->scope($instanceId, $scopeId);
            $branch = $execution->branchId === null ? false : $this->connection->fetchAssociative(
                'SELECT scope_id, status FROM approval_flow_execution_branch WHERE id = :branch FOR UPDATE',
                ['branch' => Uuid::fromHexToBytes($execution->branchId)],
            );
            if ($scope['generation'] !== $execution->generation || $branch === false || $branch['scope_id'] !== Uuid::fromHexToBytes($scopeId) || $branch['status'] !== ApprovalExecutionBranchStatus::Pending->value) {
                throw ApprovalFlowException::invalidExecutionScope($execution->nodeId, 'The execution is not an active member of this scope generation.');
            }
            while (true) {
                if ($scope['status'] !== ApprovalExecutionScopeStatus::Open) {
                    throw ApprovalFlowException::invalidExecutionScope($execution->nodeId, 'An enclosing scope has already closed.');
                }
                if ($scope['parentId'] === null) {
                    break;
                }
                $scope = $this->scope($instanceId, $scope['parentId']);
            }
        }
        if ($execution->waitingScopeId !== null && $this->scope($instanceId, $execution->waitingScopeId)['status'] !== ApprovalExecutionScopeStatus::Open) {
            throw ApprovalFlowException::invalidExecutionScope($execution->nodeId, 'The awaited scope has already closed.');
        }
    }

    public function nodeContext(string $instanceId, ApprovalExecutionState $execution): NodeExecutionContext
    {
        $formJson = $this->connection->fetchOne('SELECT form_json FROM approval_flow_request WHERE instance_id = :instance', ['instance' => Uuid::fromHexToBytes($instanceId)]);
        $snapshot = null;
        if ($execution->waitingScopeId !== null) {
            $scope = $this->scope($instanceId, $execution->waitingScopeId);
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, status FROM approval_flow_execution_branch WHERE scope_id = :scope ORDER BY id FOR UPDATE',
                ['scope' => Uuid::fromHexToBytes($scope['id'])],
            );
            $members = [];
            foreach ($rows as $row) {
                $members[Uuid::fromBytesToHex($row['id'])] = ApprovalExecutionBranchStatus::from($row['status']);
            }
            $snapshot = new ApprovalScopeSnapshot($scope['id'], $scope['generation'], $members);
        }

        return new NodeExecutionContext($formJson === false ? '{}' : $formJson, $snapshot);
    }

    public function createExecution(string $instanceId, string $nodeId, ?string $connectionId, ?string $scopeId, ?string $branchId, int $generation, Context $context, ?string $waitingScopeId = null, ApprovalNodeExecutionStatus $status = ApprovalNodeExecutionStatus::Waiting): string
    {
        $tail = $this->connection->fetchOne('SELECT `sequence` FROM approval_flow_execution WHERE instance_id = :instance ORDER BY `sequence` DESC LIMIT 1 FOR UPDATE', ['instance' => Uuid::fromHexToBytes($instanceId)]);
        $id = Uuid::randomHex();
        $row = [
            'id' => $id, 'instanceId' => $instanceId, 'nodeId' => $nodeId, 'incomingConnectionId' => $connectionId,
            'scopeId' => $scopeId, 'branchId' => $branchId, 'generation' => $generation, 'waitingScopeId' => $waitingScopeId,
            'sequence' => $tail === false ? 1 : (int) $tail + 1, 'status' => $status->value, 'advanced' => false,
        ];
        $this->writeGuard->write($context, function (Context $writeContext) use ($row): void {
            $this->executions->create([$row], $writeContext);
        });

        return $id;
    }

    public function requireNotStarted(string $instanceId): void
    {
        $existing = $this->connection->fetchOne('SELECT id FROM approval_flow_execution WHERE instance_id = :instance LIMIT 1 FOR UPDATE', ['instance' => Uuid::fromHexToBytes($instanceId)]);
        if ($existing !== false) {
            throw ApprovalFlowException::startNodeAlreadyEntered($instanceId);
        }
    }

    public function execution(string $instanceId, string $executionId): ApprovalExecutionState
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM approval_flow_execution WHERE instance_id = :instance AND id = :id FOR UPDATE', ['instance' => Uuid::fromHexToBytes($instanceId), 'id' => Uuid::fromHexToBytes($executionId)]);
        if ($row === false) {
            throw ApprovalFlowException::executionNotFound($executionId, $instanceId);
        }
        if ($this->connection->getTransactionNestingLevel() > 1 && $this->connection->fetchOne('SELECT 1 FROM approval_flow_execution WHERE id = :id', ['id' => Uuid::fromHexToBytes($executionId)]) === false) {
            throw ApprovalFlowException::executionOutsideSnapshot($executionId);
        }

        return new ApprovalExecutionState(
            id: $executionId,
            nodeId: $row['node_id'],
            status: ApprovalNodeExecutionStatus::from($row['status']),
            scopeId: $row['scope_id'] === null ? null : Uuid::fromBytesToHex($row['scope_id']),
            branchId: $row['branch_id'] === null ? null : Uuid::fromBytesToHex($row['branch_id']),
            generation: (int) $row['generation'],
            advanced: (bool) $row['advanced'],
            sequence: (int) $row['sequence'],
            openedScopeId: $row['opened_scope_id'] === null ? null : Uuid::fromBytesToHex($row['opened_scope_id']),
            waitingScopeId: $row['waiting_scope_id'] === null ? null : Uuid::fromBytesToHex($row['waiting_scope_id']),
            outcome: $row['outcome'] === null ? null : ApprovalOutcome::from($row['outcome']),
            approvalPolicy: $row['approval_policy'],
            approvalPolicyVersion: $row['approval_policy_version'],
        );
    }

    /**
     * @return list<Target>
     */
    public function returnTargets(string $instanceId, ApprovalExecutionState $source): array
    {
        // A return supersedes this lane's history from the selected target through the issuing execution.
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT candidate.id, candidate.node_id, candidate.sequence
                FROM approval_flow_execution candidate
                WHERE candidate.instance_id = :instance AND candidate.scope_id <=> :scope AND candidate.branch_id <=> :branch
                  AND candidate.generation = :generation AND candidate.sequence < :sequence AND candidate.advanced = 1
                  AND candidate.status = :completed AND candidate.outcome = :approved AND candidate.approval_policy IS NOT NULL
                  AND NOT EXISTS (
                      SELECT 1
                      FROM approval_flow_execution returned
                      INNER JOIN approval_flow_execution target ON target.id = returned.return_target_id
                      WHERE returned.instance_id = candidate.instance_id AND returned.scope_id <=> candidate.scope_id
                        AND returned.branch_id <=> candidate.branch_id AND returned.generation = candidate.generation
                        AND candidate.sequence BETWEEN target.sequence AND returned.sequence
                      FOR UPDATE
                  )
                ORDER BY candidate.sequence DESC FOR UPDATE
                SQL,
            [
                'instance' => Uuid::fromHexToBytes($instanceId),
                'scope' => $source->scopeId === null ? null : Uuid::fromHexToBytes($source->scopeId),
                'branch' => $source->branchId === null ? null : Uuid::fromHexToBytes($source->branchId),
                'generation' => $source->generation,
                'sequence' => $source->sequence,
                'completed' => ApprovalNodeExecutionStatus::Completed->value,
                'approved' => ApprovalOutcome::Approved->value,
            ],
        );

        $targets = [];
        foreach ($rows as $row) {
            if ($row['node_id'] === $source->nodeId) {
                continue;
            }

            $targets[$row['node_id']] ??= [
                'executionId' => Uuid::fromBytesToHex($row['id']),
                'nodeId' => $row['node_id'],
                'sequence' => (int) $row['sequence'],
            ];
        }

        return array_values($targets);
    }

    public function withdraw(string $instanceId, string $commandId, Context $context): void
    {
        $this->cancelWork($instanceId, null, $commandId, ApprovalTaskCompletionReason::REQUEST_WITHDRAWN, $context);
    }

    private function returnToApproval(string $instanceId, ApprovalExecutionState $source, ReturnToApproval $return, string $commandId, Context $context): void
    {
        $this->cancelPendingTasks(
            [Uuid::fromHexToBytes($source->id)],
            $commandId,
            ApprovalTaskCompletionReason::GROUP_RETURNED,
            $context,
            [$return->taskId => ApprovalTaskCompletionReason::RETURNED],
        );
        $this->updateExecution($source->id, [
            'status' => ApprovalNodeExecutionStatus::Cancelled->value,
            'advanced' => true,
            'returnTargetId' => $return->targetExecutionId,
        ], $context);
        $executionId = $this->createExecution($instanceId, $return->nodeId, null, $source->scopeId, $source->branchId, $source->generation, $context);
        $this->requestApproval($executionId, $return->tasks, $context);
    }

    private function reject(string $instanceId, string $commandId, Context $context): void
    {
        $this->cancelWork($instanceId, null, $commandId, ApprovalTaskCompletionReason::PROCESS_REJECTED, $context);
    }

    private function markAdvanced(string $executionId, Context $context): void
    {
        $this->updateExecution($executionId, ['advanced' => true], $context);
    }

    private function completeExecution(string $executionId, Context $context): void
    {
        $this->updateExecution($executionId, ['status' => ApprovalNodeExecutionStatus::Completed->value, 'advanced' => true], $context);
    }

    private function waitForArrival(string $executionId, Context $context): void
    {
        $this->updateExecution($executionId, ['status' => ApprovalNodeExecutionStatus::AwaitingSignal->value], $context);
    }

    /**
     * @param non-empty-list<array{connectionId: string, nextNodeId: string}> $routes
     */
    private function openScope(string $instanceId, ApprovalExecutionState $execution, string $joinNodeId, array $routes, Context $context): void
    {
        $scopeId = Uuid::randomHex();
        $this->writeGuard->write($context, function (Context $writeContext) use ($instanceId, $scopeId, $execution, $joinNodeId): void {
            $this->scopes->create([[
                'id' => $scopeId, 'instanceId' => $instanceId, 'parentId' => $execution->scopeId,
                'generation' => $execution->generation, 'joinNodeId' => $joinNodeId,
                'status' => ApprovalExecutionScopeStatus::Open->value,
            ]], $writeContext);
        });
        $this->updateExecution($execution->id, ['status' => ApprovalNodeExecutionStatus::Completed->value, 'advanced' => true, 'openedScopeId' => $scopeId], $context);
        // Establish the subscription with the fork, so later arrivals can wake it even from an older outer snapshot.
        $this->createExecution($instanceId, $joinNodeId, null, $execution->scopeId, $execution->branchId, $execution->generation, $context, $scopeId, ApprovalNodeExecutionStatus::AwaitingSignal);
        foreach ($routes as $route) {
            $branchId = Uuid::randomHex();
            $this->writeGuard->write($context, function (Context $writeContext) use ($scopeId, $branchId, $route): void {
                $this->branches->create([[
                    'id' => $branchId, 'scopeId' => $scopeId, 'connectionId' => $route['connectionId'],
                    'connectionHash' => Hasher::hash($route['connectionId'], 'sha256'), 'status' => ApprovalExecutionBranchStatus::Pending->value,
                ]], $writeContext);
            });
            $this->activate($instanceId, $route, $scopeId, $branchId, $execution->generation, $context);
        }
    }

    /**
     * @param non-empty-list<array{connectionId: string, nextNodeId: string}> $routes
     */
    private function activateRoutes(string $instanceId, ApprovalExecutionState $execution, array $routes, Context $context): void
    {
        foreach ($routes as $route) {
            $this->activate($instanceId, $route, $execution->scopeId, $execution->branchId, $execution->generation, $context);
        }
    }

    private function closeScope(string $instanceId, string $scopeId, string $commandId, Context $context): void
    {
        $scope = $this->scope($instanceId, $scopeId);
        if ($scope['status'] !== ApprovalExecutionScopeStatus::Open) {
            throw ApprovalFlowException::invalidExecutionScope($scope['joinNodeId'], 'The return boundary has already closed this scope.');
        }
        $this->cancelWork($instanceId, $scopeId, $commandId, ApprovalTaskCompletionReason::SCOPE_RESOLVED, $context);
        $this->writeGuard->write($context, function (Context $writeContext) use ($scopeId): void {
            $this->scopes->update([['id' => $scopeId, 'status' => ApprovalExecutionScopeStatus::Completed->value]], $writeContext);
        });
    }

    private function complete(string $instanceId, ApprovalExecutionState $execution, ApprovalOutcome $outcome, Context $context): ApprovalOutcome
    {
        if ($execution->scopeId !== null || $execution->waitingScopeId !== null) {
            throw ApprovalFlowException::invalidExecutionScope($execution->nodeId, 'A child scope must return before the process can complete.');
        }
        $unsettled = $this->connection->fetchOne(
            'SELECT id FROM approval_flow_execution WHERE instance_id = :instance AND id <> :execution AND (advanced = 0 OR status NOT IN (:completed, :cancelled)) LIMIT 1 FOR UPDATE',
            ['instance' => Uuid::fromHexToBytes($instanceId), 'execution' => Uuid::fromHexToBytes($execution->id), 'completed' => ApprovalNodeExecutionStatus::Completed->value, 'cancelled' => ApprovalNodeExecutionStatus::Cancelled->value],
        );
        $openScope = $this->connection->fetchOne(
            'SELECT id FROM approval_flow_execution_scope WHERE instance_id = :instance AND status = :status LIMIT 1 FOR UPDATE',
            ['instance' => Uuid::fromHexToBytes($instanceId), 'status' => ApprovalExecutionScopeStatus::Open->value],
        );
        if ($unsettled !== false || $openScope !== false) {
            throw ApprovalFlowException::invalidNodeProgression($execution->id, 'Other node executions are still active.');
        }
        $pending = $this->connection->fetchOne(
            'SELECT task.id FROM approval_flow_task task INNER JOIN approval_flow_execution e ON e.id = task.execution_id WHERE e.instance_id = :instance AND task.status = :pending LIMIT 1 FOR UPDATE',
            ['instance' => Uuid::fromHexToBytes($instanceId), 'pending' => ApprovalTaskStatus::Pending->value],
        );
        if ($pending !== false) {
            throw ApprovalFlowException::invalidNodeProgression($execution->id, 'Approval tasks are still pending.');
        }
        $this->updateExecution($execution->id, ['status' => ApprovalNodeExecutionStatus::Completed->value, 'advanced' => true, 'outcome' => $outcome->value], $context);

        return $outcome;
    }

    private function requestApproval(string $executionId, CreateApprovalTasks $plan, Context $context): void
    {
        $tasks = [];
        foreach ($plan->assigneeIds as $assigneeId) {
            $tasks[] = ['id' => Uuid::randomHex(), 'executionId' => $executionId, 'assigneeId' => $assigneeId, 'status' => ApprovalTaskStatus::Pending->value];
        }
        $this->writeGuard->write($context, function (Context $writeContext) use ($tasks): void {
            $this->tasks->create($tasks, $writeContext);
        });
        $this->updateExecution($executionId, ['status' => ApprovalNodeExecutionStatus::AwaitingApproval->value, 'approvalPolicy' => $plan->policy, 'approvalPolicyVersion' => $plan->policyVersion], $context);
    }

    /**
     * @param array{connectionId: string, nextNodeId: string} $route
     */
    private function activate(string $instanceId, array $route, ?string $scopeId, ?string $branchId, int $generation, Context $context): void
    {
        if ($scopeId !== null) {
            $scope = $this->scope($instanceId, $scopeId);
            if ($scope['status'] !== ApprovalExecutionScopeStatus::Open || $scope['generation'] !== $generation) {
                throw ApprovalFlowException::invalidExecutionScope($route['nextNodeId'], 'The activation belongs to an inactive scope generation.');
            }
            if ($route['nextNodeId'] === $scope['joinNodeId']) {
                $this->arrive($scope, $branchId, $route['connectionId'], $context);

                return;
            }
        }
        $this->createExecution($instanceId, $route['nextNodeId'], $route['connectionId'], $scopeId, $branchId, $generation, $context);
    }

    /**
     * @param Scope $scope
     */
    private function arrive(array $scope, ?string $branchId, string $connectionId, Context $context): void
    {
        $branch = $branchId === null ? false : $this->connection->fetchAssociative(
            'SELECT scope_id, status FROM approval_flow_execution_branch WHERE id = :id FOR UPDATE',
            ['id' => Uuid::fromHexToBytes($branchId)],
        );
        if ($branch === false || $branch['scope_id'] !== Uuid::fromHexToBytes($scope['id']) || $branch['status'] !== ApprovalExecutionBranchStatus::Pending->value) {
            throw ApprovalFlowException::invalidExecutionScope($scope['joinNodeId'], 'An arrival must resolve one active member of this scope.');
        }
        $this->writeGuard->write($context, function (Context $writeContext) use ($branchId, $connectionId): void {
            $this->branches->update([['id' => $branchId, 'status' => ApprovalExecutionBranchStatus::Arrived->value, 'arrivalConnectionId' => $connectionId]], $writeContext);
        });
        $waiting = $this->connection->fetchOne(
            'SELECT id FROM approval_flow_execution WHERE waiting_scope_id = :scope FOR UPDATE',
            ['scope' => Uuid::fromHexToBytes($scope['id'])],
        );
        if ($waiting === false) {
            throw ApprovalFlowException::invalidExecutionScope($scope['joinNodeId'], 'The scope arrival subscription is missing.');
        }
        $this->updateExecution(Uuid::fromBytesToHex($waiting), ['status' => ApprovalNodeExecutionStatus::Waiting->value], $context);
    }

    private function cancelWork(string $instanceId, ?string $scopeId, string $commandId, string $reason, Context $context): void
    {
        $scopeRows = $this->connection->fetchAllAssociative(
            'SELECT id, parent_id, status FROM approval_flow_execution_scope WHERE instance_id = :instance FOR UPDATE',
            ['instance' => Uuid::fromHexToBytes($instanceId)],
        );
        $selected = $scopeId === null ? array_column($scopeRows, 'id') : [Uuid::fromHexToBytes($scopeId)];
        // Expand descendants from current locking reads; a nested scope can have its own suspended join.
        do {
            $previousCount = \count($selected);
            foreach ($scopeRows as $scope) {
                if (\in_array($scope['parent_id'], $selected, true) && !\in_array($scope['id'], $selected, true)) {
                    $selected[] = $scope['id'];
                }
            }
        } while (\count($selected) !== $previousCount);
        $parameters = ['instance' => Uuid::fromHexToBytes($instanceId)];
        $filter = '';
        $types = [];
        if ($scopeId !== null) {
            $filter = ' AND (scope_id IN (:scopes) OR waiting_scope_id IN (:descendants))';
            $parameters['scopes'] = $selected;
            $parameters['descendants'] = array_values(array_filter($selected, static fn (string $id): bool => $id !== Uuid::fromHexToBytes($scopeId)));
            $types = ['scopes' => ArrayParameterType::BINARY, 'descendants' => ArrayParameterType::BINARY];
        }
        $executions = $this->connection->fetchAllAssociative(
            'SELECT id, status FROM approval_flow_execution WHERE instance_id = :instance AND advanced = 0' . $filter . ' FOR UPDATE',
            $parameters,
            $types,
        );
        $ids = array_column($executions, 'id');
        if ($ids !== []) {
            $this->cancelPendingTasks($ids, $commandId, $reason, $context);
            foreach ($executions as $execution) {
                $updates = ['advanced' => true];
                if ($execution['status'] !== ApprovalNodeExecutionStatus::Completed->value) {
                    $updates['status'] = ApprovalNodeExecutionStatus::Cancelled->value;
                }
                // Committed opinions and node outcomes survive cancellation; only dispatch eligibility ends.
                $this->updateExecution(Uuid::fromBytesToHex($execution['id']), $updates, $context);
            }
        }
        if ($selected !== []) {
            $pendingBranches = $this->connection->fetchFirstColumn(
                'SELECT id FROM approval_flow_execution_branch WHERE scope_id IN (:scopes) AND status = :status FOR UPDATE',
                ['scopes' => $selected, 'status' => ApprovalExecutionBranchStatus::Pending->value],
                ['scopes' => ArrayParameterType::BINARY],
            );
            if ($pendingBranches !== []) {
                $updates = array_map(static fn (string $id): array => ['id' => Uuid::fromBytesToHex($id), 'status' => ApprovalExecutionBranchStatus::Cancelled->value], $pendingBranches);
                $this->writeGuard->write($context, function (Context $writeContext) use ($updates): void {
                    $this->branches->update($updates, $writeContext);
                });
            }
            $updates = [];
            foreach ($scopeRows as $scope) {
                if (\in_array($scope['id'], $selected, true) && $scope['status'] === ApprovalExecutionScopeStatus::Open->value) {
                    $updates[] = ['id' => Uuid::fromBytesToHex($scope['id']), 'status' => ApprovalExecutionScopeStatus::Cancelled->value];
                }
            }
            if ($updates !== []) {
                $this->writeGuard->write($context, function (Context $writeContext) use ($updates): void {
                    $this->scopes->update($updates, $writeContext);
                });
            }
        }
    }

    /**
     * @param list<string> $executionIds Binary execution UUIDs
     * @param array<string, string> $taskReasons
     */
    private function cancelPendingTasks(array $executionIds, string $commandId, string $reason, Context $context, array $taskReasons = []): void
    {
        $taskRows = $this->connection->fetchFirstColumn(
            'SELECT id FROM approval_flow_task WHERE execution_id IN (:executions) AND status = :pending FOR UPDATE',
            ['executions' => $executionIds, 'pending' => ApprovalTaskStatus::Pending->value],
            ['executions' => ArrayParameterType::BINARY],
        );
        if ($taskRows === []) {
            return;
        }

        $updates = [];
        foreach ($taskRows as $row) {
            $id = Uuid::fromBytesToHex($row);
            $updates[] = [
                'id' => $id,
                'status' => ApprovalTaskStatus::Cancelled->value,
                'completionCommandId' => $commandId,
                'completionReason' => $taskReasons[$id] ?? $reason,
            ];
        }
        $this->writeGuard->write($context, function (Context $writeContext) use ($updates): void {
            $this->tasks->update($updates, $writeContext);
        });
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function updateExecution(string $id, array $changes, Context $context): void
    {
        $this->writeGuard->write($context, function (Context $writeContext) use ($id, $changes): void {
            $this->executions->update([['id' => $id, ...$changes]], $writeContext);
        });
    }

    /**
     * @return Scope
     */
    private function scope(string $instanceId, string $scopeId): array
    {
        $row = $this->connection->fetchAssociative('SELECT id, parent_id, generation, join_node_id, status FROM approval_flow_execution_scope WHERE instance_id = :instance AND id = :id FOR UPDATE', ['instance' => Uuid::fromHexToBytes($instanceId), 'id' => Uuid::fromHexToBytes($scopeId)]);
        if ($row === false) {
            throw ApprovalFlowException::invalidExecutionScope($scopeId, 'The scope does not belong to this instance.');
        }

        return ['id' => $scopeId, 'parentId' => $row['parent_id'] === null ? null : Uuid::fromBytesToHex($row['parent_id']), 'generation' => (int) $row['generation'], 'joinNodeId' => $row['join_node_id'], 'status' => ApprovalExecutionScopeStatus::from($row['status'])];
    }
}
