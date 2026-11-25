<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Query;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStore;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionCollection;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Operation\ApprovalOperationCollection;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskStatus;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestCollection;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\User\UserCollection;

/**
 * Personal approval queries apply ownership before pagination and return explicit projections.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Query\ApprovalRequestQueryTest
 *
 * @phpstan-import-type GraphPayload from GraphDiagnostics
 *
 * @phpstan-type RequestSummary array{id: string, title: string, businessType: string, businessId: string, submissionRound: int, instanceId: string, initiatorId: string, initiatorName: string|null, createdAt: string|null, status: string, outcome: string|null, aggregateVersion: int}
 * @phpstan-type ExecutionSummary array{id: string, nodeId: string, sequence: int, status: string, outcome: string|null}
 * @phpstan-type TaskSummary array{id: string, executionId: string, assigneeId: string, status: string}
 * @phpstan-type DecisionSummary array{taskId: string, nodeId: string, assigneeId: string, decision: string, reason: string, decidedAt: string|null, aggregateVersion: int}
 */
#[Package('framework')]
class ApprovalRequestQuery
{
    /**
     * @param EntityRepository<ApprovalRequestCollection> $requests
     * @param EntityRepository<ApprovalNodeExecutionCollection> $executions
     * @param EntityRepository<ApprovalOperationCollection> $operations
     * @param EntityRepository<UserCollection> $users
     */
    public function __construct(
        private readonly EntityRepository $requests,
        private readonly EntityRepository $executions,
        private readonly EntityRepository $operations,
        private readonly ApprovalFormReader $forms,
        private readonly EntityRepository $users,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array{data: list<RequestSummary>, meta: array{total: int}}
     */
    public function search(ApprovalRequestView $view, int $limit, int $offset, ?ApprovalInstanceStatus $status, Context $context, string $term = ''): array
    {
        $userId = $this->userId($context);
        $criteria = (new Criteria())
            ->addAssociation('instance.release')
            ->setLimit($limit)
            ->setOffset($offset)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT)
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING), new FieldSorting('id', FieldSorting::DESCENDING));
        if ($view === ApprovalRequestView::Started) {
            $criteria->addFilter(new EqualsFilter('initiatorId', $userId));
        } else {
            $statuses = $view === ApprovalRequestView::Todo
                ? [ApprovalTaskStatus::Pending->value]
                : [ApprovalTaskStatus::Approved->value, ApprovalTaskStatus::Rejected->value];
            $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('instance.executions.tasks.assigneeId', $userId),
                new EqualsAnyFilter('instance.executions.tasks.status', $statuses),
            ]));
            if ($view === ApprovalRequestView::Todo) {
                $criteria->addFilter(new EqualsFilter('instance.status', ApprovalInstanceStatus::Running->value));
            }
        }
        if ($status !== null) {
            $criteria->addFilter(new EqualsFilter('instance.status', $status->value));
        }
        if ($term !== '') {
            $releaseIds = $this->findReleasesByTitle($term);
            $criteria->addFilter(new EqualsAnyFilter('instance.releaseId', $releaseIds));
        }
        $result = $this->requests->search($criteria, $context);
        $names = $this->userNames(array_values($result->getEntities()->map(static fn (ApprovalRequestEntity $request): string => $request->getInitiatorId())), $context);

        return [
            'data' => array_values($result->getEntities()->map(fn (ApprovalRequestEntity $request): array => $this->summary($request, $names[$request->getInitiatorId()] ?? null))),
            'meta' => ['total' => $result->getTotal()],
        ];
    }

    /**
     * @return array{request: RequestSummary, form: \stdClass, formLabels: \stdClass, participantNames: \stdClass, executions: list<ExecutionSummary>, tasks: list<TaskSummary>, decisions: list<DecisionSummary>}
     */
    public function detail(string $requestId, Context $context): array
    {
        $userId = $this->userId($context);
        $criteria = (new Criteria([$requestId]))->addAssociation('instance.release')->addAssociation('binding');
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('initiatorId', $userId),
            new EqualsFilter('instance.executions.tasks.assigneeId', $userId),
        ]));
        $request = $this->requests->search($criteria, $context)->getEntities()->first();
        if ($request === null) {
            throw ApprovalFlowException::requestNotFound($requestId);
        }

        $executionCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('instanceId', $request->getInstanceId()))
            ->addSorting(new FieldSorting('sequence'))
            ->addAssociation('tasks');
        $executionCriteria->getAssociation('tasks')->addSorting(new FieldSorting('id'));
        $executions = $this->executions->search($executionCriteria, $context)->getEntities();
        $operationCriteria = (new Criteria())->addFilter(
            new EqualsFilter('instanceId', $request->getInstanceId()),
            new EqualsFilter('operation', ApprovalInstanceStore::DECIDE),
        );
        $operations = $this->operations->search($operationCriteria, $context)->getEntities();
        $receipts = [];
        foreach ($operations as $operation) {
            $receipts[$operation->getCommandId()] = $operation;
        }

        $executionSummaries = [];
        $tasks = [];
        $decisions = [];
        $assignedNodeIds = [];
        foreach ($executions as $execution) {
            $executionSummaries[] = [
                'id' => $execution->getId(), 'nodeId' => $execution->getNodeId(), 'sequence' => $execution->getSequence(),
                'status' => $execution->getStatus()->value, 'outcome' => $execution->getOutcome()?->value,
            ];
            $executionTasks = $execution->getTasks();
            \assert($executionTasks !== null);
            foreach ($executionTasks as $task) {
                $tasks[] = ['id' => $task->getId(), 'executionId' => $execution->getId(), 'assigneeId' => $task->getAssigneeId(), 'status' => $task->getStatus()->value];
                if ($task->getAssigneeId() === $userId) {
                    $assignedNodeIds[] = $execution->getNodeId();
                }
                if (!\in_array($task->getStatus(), [ApprovalTaskStatus::Approved, ApprovalTaskStatus::Rejected], true)) {
                    continue;
                }
                $commandId = $task->getCompletionCommandId();
                \assert($commandId !== null);
                $receipt = $receipts[$commandId];
                $decisions[] = [
                    'taskId' => $task->getId(), 'nodeId' => $execution->getNodeId(), 'assigneeId' => $task->getAssigneeId(),
                    'decision' => $task->getStatus()->value, 'reason' => $receipt->getReason(), 'decidedAt' => $receipt->getCreatedAt()?->format(\DATE_ATOM),
                    'aggregateVersion' => $receipt->getAggregateVersion(),
                ];
            }
        }
        usort($decisions, static fn (array $first, array $second): int => $first['aggregateVersion'] <=> $second['aggregateVersion']);
        $instance = $request->getInstance();
        \assert($instance !== null);
        $release = $instance->getRelease();
        \assert($release !== null);
        $form = $request->getInitiatorId() === $userId
            ? json_decode($request->getFormJson(), flags: \JSON_THROW_ON_ERROR)
            : $this->forms->read($request->getFormJson(), $release->getNodePlanJson(), $assignedNodeIds);
        \assert($form instanceof \stdClass);
        $binding = $request->getBinding();
        \assert($binding !== null);
        $names = $this->userNames([$request->getInitiatorId(), ...array_column($tasks, 'assigneeId')], $context);

        return [
            'request' => $this->summary($request, $names[$request->getInitiatorId()] ?? null),
            'form' => $form,
            'formLabels' => $this->forms->labels($form, $binding->getFormSchema()),
            'participantNames' => (object) $names,
            'executions' => $executionSummaries,
            'tasks' => $tasks,
            'decisions' => $decisions,
        ];
    }

    /**
     * @return RequestSummary
     */
    private function summary(ApprovalRequestEntity $request, ?string $initiatorName): array
    {
        $instance = $request->getInstance();
        \assert($instance !== null);
        $release = $instance->getRelease();
        \assert($release !== null);
        /** @var GraphPayload $graph */
        $graph = json_decode($release->getGraphJson(), flags: \JSON_THROW_ON_ERROR);

        return [
            'id' => $request->getId(),
            'title' => $graph->meta->name,
            'businessType' => $request->getBusinessType(),
            'businessId' => $request->getBusinessId(),
            'submissionRound' => $request->getSubmissionRound(),
            'instanceId' => $request->getInstanceId(),
            'initiatorId' => $request->getInitiatorId(),
            'initiatorName' => $initiatorName,
            'createdAt' => $request->getCreatedAt()?->format(\DATE_ATOM),
            'status' => $instance->getStatus()->value,
            'outcome' => $instance->getOutcome()?->value,
            'aggregateVersion' => $instance->getAggregateVersion(),
        ];
    }

    /**
     * @return list<string>
     */
    private function findReleasesByTitle(string $term): array
    {
        // Only the title is visible in the list. The rest of the frozen graph can contain private configuration.
        $ids = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT id
                FROM approval_flow_release
                WHERE LOCATE(
                    :term,
                    CONVERT(JSON_UNQUOTE(JSON_EXTRACT(graph_json, '$.meta.name')) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                ) > 0
                SQL,
            ['term' => $term],
        );

        return array_map(static fn (string $id): string => Uuid::fromBytesToHex($id), $ids);
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, string>
     */
    private function userNames(array $ids, Context $context): array
    {
        if ($ids === []) {
            return [];
        }
        $criteria = (new Criteria(array_values(array_unique($ids))))->addFields(['id', 'name']);
        $names = [];
        foreach ($this->users->search($criteria, $context)->getEntities() as $user) {
            $name = $user->get('name');
            \assert(\is_string($name));
            $names[$user->getId()] = $name;
        }

        return $names;
    }

    private function userId(Context $context): string
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource || $source->getUserId() === null || $source->getIntegrationId() !== null) {
            throw ApprovalFlowException::requestActorRequired();
        }

        return $source->getUserId();
    }
}
