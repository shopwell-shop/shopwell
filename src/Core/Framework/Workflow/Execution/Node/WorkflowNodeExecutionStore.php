<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Node;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\NodeOutcome;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowSignal;

/**
 * @internal Writes are serialized by the owning instance transaction.
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Execution\WorkflowSkeletonExecutionTest
 */
#[Package('framework')]
final readonly class WorkflowNodeExecutionStore
{
    /**
     * @param EntityRepository<WorkflowNodeExecutionCollection> $executions
     */
    public function __construct(private EntityRepository $executions, private ClockInterface $clock)
    {
    }

    public function enter(GraphNode $node, WorkflowExecutionContext $context, ?string $sourceConnectionId = null): string
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('instanceId', $context->instance()->id))
            ->addSorting(new FieldSorting('sequence', FieldSorting::DESCENDING))->setLimit(1);
        $previous = $this->executions->search($criteria, $context->context())->getEntities()->first();
        $sequence = $previous === null ? 1 : $previous->sequence + 1;
        $id = Uuid::randomHex();
        $this->executions->create([[
            'id' => $id,
            'instanceId' => $context->instance()->id,
            'nodeId' => $node->id,
            'round' => $context->round(),
            'sequence' => $sequence,
            'sourceConnectionId' => $sourceConnectionId,
            'status' => WorkflowNodeExecutionEntity::RUNNING,
            'startedAt' => $this->clock->now(),
        ]], $context->context());

        return $id;
    }

    public function get(string $id, Context $context): WorkflowNodeExecutionEntity
    {
        return $this->executions->search(new Criteria([$id]), $context)->getEntities()->first()
            ?? throw WorkflowExecutionException::executionNotWaiting($id);
    }

    public function waiting(string $id, string $instanceId, WorkflowSignal $signal, Context $context): WorkflowNodeExecutionEntity
    {
        $execution = $this->get($id, $context);

        if ($execution->instanceId !== $instanceId || $execution->status !== WorkflowNodeExecutionEntity::WAITING) {
            throw WorkflowExecutionException::executionNotWaiting($id);
        }

        if ($execution->waitingFor !== $signal->name) {
            throw WorkflowExecutionException::invalidSignal($signal->name);
        }

        return $execution;
    }

    public function record(string $id, NodeOutcome $outcome, Context $context): void
    {
        $this->executions->update([[
            'id' => $id,
            'status' => $outcome->isWaiting() ? WorkflowNodeExecutionEntity::WAITING : WorkflowNodeExecutionEntity::COMPLETED,
            'waitingFor' => $outcome->waitingFor,
            'result' => $outcome->result,
            'output' => $outcome->output,
            'endedAt' => $outcome->isWaiting() ? null : $this->clock->now(),
        ]], $context);
    }

    public function cancel(string $instanceId, Context $context): void
    {
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('instanceId', $instanceId),
            new EqualsAnyFilter('status', [WorkflowNodeExecutionEntity::RUNNING, WorkflowNodeExecutionEntity::WAITING]),
        );
        $updates = [];

        foreach ($this->executions->search($criteria, $context)->getEntities() as $execution) {
            $updates[] = [
                'id' => $execution->id,
                'status' => WorkflowNodeExecutionEntity::CANCELED,
                'waitingFor' => null,
                'endedAt' => $this->clock->now(),
            ];
        }

        if ($updates !== []) {
            $this->executions->update($updates, $context);
        }
    }
}
