<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Command\ApprovalExecutionCommandContext;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\NodeEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\OpenScopeEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\RequestApprovalEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Event\ApprovalReturnTargetsEvent;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\ApprovalExecutionPlan;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\CreateApprovalTasks;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\ReturnToApproval;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalAssigneeResolver;
use Shopwell\Core\Framework\Log\Package;

/**
 * Prepares current facts and verified entry output before invoking the pure planner.
 *
 * @internal
 *
 * @phpstan-import-type Target from ApprovalReturnTargetsEvent
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalSerialProgressionTest
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalParallelExecutionTest
 */
#[Package('framework')]
class ApprovalExecutionPreparation
{
    public function __construct(
        private readonly ApprovalExecutionStore $store,
        private readonly ApprovalNodeEntry $nodes,
        private readonly ApprovalTransitionPlanner $planner,
        private readonly ApprovalAssigneeResolver $assignees,
    ) {
    }

    public function enter(ApprovalExecutionCommandContext $command, ExecutableApprovalRelease $release, ApprovalExecutionState $execution): ApprovalExecutionPlan
    {
        $this->store->requireActive($command->instanceId, $execution);
        $facts = $this->store->nodeContext($command->instanceId, $execution);
        $effect = $this->nodes->enter($release, $execution->nodeId, $facts);

        return $this->fromEffect($command, $release, $execution, $effect);
    }

    /**
     * @param Target $target
     */
    public function returnToApproval(ApprovalExecutionCommandContext $command, ExecutableApprovalRelease $release, ApprovalExecutionState $source, string $taskId, array $target): ApprovalExecutionPlan
    {
        $effect = $this->nodes->enter($release, $target['nodeId'], $this->store->nodeContext($command->instanceId, $source));
        if (!$effect instanceof RequestApprovalEffect) {
            throw ApprovalFlowException::invalidNodeProgression($source->id, 'The return target must open an approval group.');
        }

        return new ApprovalExecutionPlan($source, new ReturnToApproval($taskId, $target['executionId'], $target['nodeId'], $this->approvalTasks($effect)));
    }

    /**
     * The caller has already admitted this execution and evaluated the exact node entry.
     */
    public function fromEffect(ApprovalExecutionCommandContext $command, ExecutableApprovalRelease $release, ApprovalExecutionState $execution, NodeEffect $effect): ApprovalExecutionPlan
    {
        $intent = $effect instanceof RequestApprovalEffect ? $this->approvalTasks($effect) : $effect;
        $boundaries = $effect instanceof OpenScopeEffect
            ? $this->store->enclosingScopeBoundaries($command->instanceId, $execution->scopeId)
            : [];

        return $this->planner->enter($release->graph, $execution, $intent, $boundaries);
    }

    private function approvalTasks(RequestApprovalEffect $effect): CreateApprovalTasks
    {
        $configuration = $effect->configuration;
        $assignees = $this->assignees->resolveAssignees($configuration);

        return new CreateApprovalTasks($assignees, $configuration->policy, $configuration->policyVersion);
    }
}
