<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalModeRegistry;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalVote;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalVotes;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskCollection;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStates;

/**
 * Project counted task decisions into the registered approval strategy.
 * Callers serialize concurrent decisions for one node and round.
 *
 * @internal
 */
#[Package('framework')]
final readonly class CounterSignCalculator
{
    public function __construct(private ApprovalModeRegistry $modes)
    {
    }

    /**
     * @param int|null $expectedTotal Full assignee snapshot size for sequential approval
     * @param array<string, mixed> $configuration
     */
    public function resolve(
        WorkflowTaskCollection $tasks,
        string $countersignType,
        ?int $expectedTotal = null,
        array $configuration = [],
        int $version = 1,
    ): CounterSignResult {
        $mode = $this->modes->forType($countersignType, $version);
        $mode->validateConfiguration($configuration);
        $counted = $tasks->filterByStates(WorkflowTaskStates::COUNTED_STATES);

        if ($counted->count() === 0) {
            return CounterSignResult::pending(0, 0, 0);
        }

        $votes = [];

        foreach ($counted as $task) {
            $votes[] = new ApprovalVote($task->assigneeId, $task->state(), $task->sequence);
        }

        return $mode->resolve(new ApprovalVotes(
            $votes,
            $counted->countByState(WorkflowTaskStates::AGREED),
            $counted->countByState(WorkflowTaskStates::REJECTED),
            $counted->count(),
            $expectedTotal
        ), $configuration);
    }
}
