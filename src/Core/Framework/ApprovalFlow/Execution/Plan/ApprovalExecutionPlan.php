<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Plan;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionState;
use Shopwell\Core\Framework\Log\Package;

/**
 * Ordered semantic changes to one locked execution; never a list of DAL patches.
 * Plans remain internal while cancellation, re-entry and control actions are developed.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalExecutionPlan
{
    /**
     * @var non-empty-list<ExecutionChange>
     */
    public array $steps;

    public function __construct(
        public ApprovalExecutionState $execution,
        ExecutionChange $first,
        ExecutionChange ...$remaining,
    ) {
        $steps = [$first, ...array_values($remaining)];
        $consumed = false;
        $finished = false;
        $closedScope = false;
        foreach ($steps as $step) {
            if (!$step instanceof ActivateRoutes && !$step instanceof CloseScope && !$step instanceof CompleteExecution
                && !$step instanceof CompleteInstance && !$step instanceof CreateApprovalTasks && !$step instanceof MarkExecutionAdvanced
                && !$step instanceof OpenScope && !$step instanceof RejectInstance && !$step instanceof WaitForArrival) {
                throw ApprovalFlowException::invalidExecutionPlan('Unsupported execution change ' . $step::class . '.');
            }
            if ($finished) {
                throw ApprovalFlowException::invalidExecutionPlan('No change may follow an activation, wait or terminal result.');
            }
            if ($step instanceof CloseScope) {
                if ($closedScope || $consumed || $step->scopeId !== $execution->waitingScopeId) {
                    throw ApprovalFlowException::invalidExecutionPlan('Close the current return scope before consuming its execution.');
                }
                $closedScope = true;
                continue;
            }
            if ($step instanceof ActivateRoutes) {
                if (!$consumed) {
                    throw ApprovalFlowException::invalidExecutionPlan('Complete or advance the current execution before activating its routes.');
                }
                $finished = true;
                continue;
            }
            if ($consumed) {
                throw ApprovalFlowException::invalidExecutionPlan('A plan may consume its current execution only once.');
            }
            if ($closedScope && !$step instanceof CompleteExecution) {
                throw ApprovalFlowException::invalidExecutionPlan('A closed return scope must continue through a completed execution.');
            }
            $consumed = true;
            $finished = !$step instanceof CompleteExecution && !$step instanceof MarkExecutionAdvanced;
        }
        if (!$finished) {
            throw ApprovalFlowException::invalidExecutionPlan('A plan must finish at an activation, wait or terminal result.');
        }
        $this->steps = $steps;
    }
}
