<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\AdvanceEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\CompleteEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\NodeEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\OpenScopeEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\WaitEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\ActivateRoutes;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\ApprovalExecutionPlan;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\CloseScope;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\CompleteExecution;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\CompleteInstance;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\CreateApprovalTasks;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\MarkExecutionAdvanced;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\OpenScope;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\RejectInstance;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\WaitForArrival;
use Shopwell\Core\Framework\Log\Package;

/**
 * Converts verified entry intents and prepared facts into ordered changes, without performing I/O.
 *
 * @internal
 */
#[Package('framework')]
class ApprovalTransitionPlanner
{
    /**
     * @param list<string> $ancestorBoundaries
     */
    public function enter(
        ApprovalExecutionGraph $graph,
        ApprovalExecutionState $execution,
        NodeEffect|CreateApprovalTasks $intent,
        array $ancestorBoundaries = [],
    ): ApprovalExecutionPlan {
        if ($intent instanceof CreateApprovalTasks) {
            if ($execution->waitingScopeId !== null) {
                throw ApprovalFlowException::invalidExecutionScope($execution->nodeId, 'A return boundary must wait or advance.');
            }

            return new ApprovalExecutionPlan($execution, $intent);
        }
        if ($intent instanceof WaitEffect) {
            if ($execution->waitingScopeId === null) {
                throw ApprovalFlowException::invalidExecutionScope($execution->nodeId, 'Waiting requires a runtime-owned scope arrival subscription.');
            }

            return new ApprovalExecutionPlan($execution, new WaitForArrival());
        }
        if ($intent instanceof OpenScopeEffect) {
            if ($execution->waitingScopeId !== null) {
                throw ApprovalFlowException::invalidExecutionScope($execution->nodeId, 'A return boundary must close its scope before opening another.');
            }
            $routes = $graph->successors($execution->nodeId, $intent->outputPorts);
            $graph->validateScope($execution->nodeId, $intent->joinNodeId, $routes, $ancestorBoundaries);

            return new ApprovalExecutionPlan($execution, new OpenScope($routes, $intent->joinNodeId));
        }
        if ($intent instanceof AdvanceEffect) {
            $routes = $graph->successors($execution->nodeId, $intent->outputPorts);
            if (\count($routes) !== 1) {
                throw ApprovalFlowException::invalidExecutionScope($execution->nodeId, 'Multiple activations require an explicit child scope.');
            }
            $steps = [];
            if ($execution->waitingScopeId !== null) {
                $steps[] = new CloseScope($execution->waitingScopeId);
            }

            $steps[] = new CompleteExecution();
            $steps[] = new ActivateRoutes($routes);

            return new ApprovalExecutionPlan($execution, ...$steps);
        }
        if (!$intent instanceof CompleteEffect) {
            throw ApprovalFlowException::invalidExecutionPlan('Unsupported prepared node intent ' . $intent::class . '.');
        }
        if ($graph->endNodeId !== $execution->nodeId) {
            throw ApprovalFlowException::invalidGraphPlan('Only the published end boundary may complete the process.');
        }

        return new ApprovalExecutionPlan($execution, new CompleteInstance($intent->outcome));
    }

    public function advance(ApprovalExecutionGraph $graph, ApprovalExecutionState $execution): ApprovalExecutionPlan
    {
        return new ApprovalExecutionPlan($execution, new MarkExecutionAdvanced(), new ActivateRoutes($graph->successors($execution->nodeId)));
    }

    public function reject(ApprovalExecutionState $execution): ApprovalExecutionPlan
    {
        return new ApprovalExecutionPlan($execution, new RejectInstance());
    }
}
