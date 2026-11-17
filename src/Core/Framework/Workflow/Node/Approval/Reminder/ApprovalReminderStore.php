<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Reminder;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceCollection;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalTaskActions;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskCollection;

/**
 * Create private receipts inside the task's transaction, including transferred and added tasks.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderServiceTest
 */
#[Package('framework')]
final readonly class ApprovalReminderStore
{
    /**
     * @param EntityRepository<ApprovalReminderCollection> $reminders
     * @param EntityRepository<WorkflowTaskCollection> $tasks
     * @param EntityRepository<WorkflowInstanceCollection> $instances
     */
    public function __construct(private EntityRepository $reminders, private EntityRepository $tasks, private EntityRepository $instances)
    {
    }

    /**
     * @param list<string> $taskIds
     */
    public function initialize(array $taskIds, Context $context): void
    {
        if ($taskIds === []) {
            return;
        }

        $tasks = $this->tasks->search(new Criteria($taskIds), $context)->getEntities();
        $instanceIds = [];
        foreach ($tasks as $task) {
            $instanceIds[] = $task->instanceId;
        }
        $instances = $this->instances->search(new Criteria(array_values(array_unique($instanceIds))), $context)->getEntities();
        $payload = [];

        foreach ($tasks as $task) {
            $instance = $instances->get($task->instanceId)
                ?? throw WorkflowExecutionException::instanceNotFound($task->instanceId);
            $node = GraphData::fromArray($instance->snapshot())->node($task->nodeId);
            if ($node->property('approvalStage') !== true) {
                continue;
            }

            $dueAt = null;
            $remind = false;
            if (\array_key_exists('approvalDeadline', $node->properties)) {
                $deadline = ApprovalDeadline::fromConfiguration($node->properties['approvalDeadline']);
                $createdAt = $task->getCreatedAt() ?? throw WorkflowExecutionException::invalidGraph('An approval task must have a creation timestamp.');
                $dueAt = $deadline->dueAt($createdAt);
                $remind = $deadline->remind;
            }

            $sourceId = $task->ext['sourceTaskId'] ?? null;
            $isAddedReviewer = ($task->ext[ApprovalTaskActions::EXT_CREATION_ACTION] ?? null) === ApprovalTaskActions::ADD_SIGN;
            if (\is_string($sourceId) && !$isAddedReviewer) {
                $source = $this->get($sourceId, $context)
                    ?? throw WorkflowExecutionException::invalidGraph('A transferred approval task requires its source reminder receipt.');
                $dueAt = $source->dueAt;
                $remind = $source->remindOnOverdue;
            }

            $payload[] = [
                'id' => $task->id,
                'taskId' => $task->id,
                'instanceId' => $task->instanceId,
                'dueAt' => $dueAt,
                'remindOnOverdue' => $remind,
                'reminderCount' => 0,
            ];
        }

        if ($payload !== []) {
            $this->reminders->create($payload, $context);
        }
    }

    public function get(string $taskId, Context $context): ?ApprovalReminderEntity
    {
        return $this->reminders->search(new Criteria([$taskId]), $context)->getEntities()->first();
    }

    /**
     * @param list<string> $taskIds
     */
    public function forTasks(array $taskIds, Context $context): ApprovalReminderCollection
    {
        if ($taskIds === []) {
            return new ApprovalReminderCollection();
        }

        return $this->reminders->search(new Criteria($taskIds), $context)->getEntities();
    }
}
