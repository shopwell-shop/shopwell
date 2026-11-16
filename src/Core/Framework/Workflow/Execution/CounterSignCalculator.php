<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskCollection;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStates;

/**
 * Pure approval aggregation for one node and round; canceled tasks are excluded and callers serialize concurrent decisions.
 *
 * @internal
 */
#[Package('framework')]
final class CounterSignCalculator
{
    /**
     * @param WorkflowTaskCollection $tasks Tasks for one node and round
     * @param int|null $expectedTotal Full assignee snapshot size for sequential approval; null for other modes
     */
    public function resolve(WorkflowTaskCollection $tasks, string $countersignType, ?int $expectedTotal = null): CounterSignResult
    {
        // Exclude canceled or skipped tasks from the denominator.
        $counted = $tasks->filterByStates(WorkflowTaskStates::COUNTED_STATES);

        $agreed = $counted->countByState(WorkflowTaskStates::AGREED);
        $rejected = $counted->countByState(WorkflowTaskStates::REJECTED);
        $total = $counted->count();

        // An empty result indicates an invalid task subset; keep execution pending.
        if ($total === 0) {
            return CounterSignResult::pending(0, 0, 0);
        }

        return match ($countersignType) {
            // The single task determines the outcome.
            WorkflowCountersignTypes::SINGLE => match (true) {
                $agreed === 1 => CounterSignResult::agreed($agreed, $rejected, $total),
                $rejected === 1 => CounterSignResult::rejected($agreed, $rejected, $total),
                default => CounterSignResult::pending($agreed, $rejected, $total),
            },

            // Any approval passes; every task must reject before the node is rejected.
            WorkflowCountersignTypes::OR_SIGN => match (true) {
                $agreed > 0 => CounterSignResult::agreed($agreed, $rejected, $total),
                $rejected === $total => CounterSignResult::rejected($agreed, $rejected, $total),
                default => CounterSignResult::pending($agreed, $rejected, $total),
            },

            // The instance lock admits only the first decision; remaining tasks are canceled afterward.
            WorkflowCountersignTypes::FIRST_DECISION => match (true) {
                $rejected > 0 => CounterSignResult::rejected($agreed, $rejected, $total),
                $agreed > 0 => CounterSignResult::agreed($agreed, $rejected, $total),
                default => CounterSignResult::pending($agreed, $rejected, $total),
            },

            // All tasks must approve; any rejection rejects the node.
            WorkflowCountersignTypes::COUNTERSIGN => match (true) {
                $rejected > 0 => CounterSignResult::rejected($agreed, $rejected, $total),
                $agreed === $total => CounterSignResult::agreed($agreed, $rejected, $total),
                default => CounterSignResult::pending($agreed, $rejected, $total),
            },

            // Sequential approval passes only after the final assignee approves.
            WorkflowCountersignTypes::ORDER_SIGN => match (true) {
                $rejected > 0 => CounterSignResult::rejected($agreed, $rejected, $total),
                // Use the complete assignee snapshot because sequential tasks are created one at a time.
                ($expectedTotal !== null ? $agreed >= $expectedTotal : $counted->lastSequenceIsAgreed()) => CounterSignResult::agreed($agreed, $rejected, $total),
                default => CounterSignResult::pending($agreed, $rejected, $total),
            },

            default => throw WorkflowExecutionException::unsupportedCountersignType($countersignType),
        };
    }
}
