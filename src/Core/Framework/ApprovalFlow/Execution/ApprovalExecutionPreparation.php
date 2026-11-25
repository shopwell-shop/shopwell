<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution;

use Shopwell\Core\Framework\ApprovalFlow\Execution\Command\ApprovalExecutionCommandContext;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\NodeEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\OpenScopeEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\RequestApprovalEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\ApprovalExecutionPlan;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\CreateApprovalTasks;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalAssigneeResolver;
use Shopwell\Core\Framework\Log\Package;

/**
 * Prepares current facts and verified entry output before invoking the pure planner.
 *
 * @internal
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
     * The caller has already admitted this execution and evaluated the exact node entry.
     */
    public function fromEffect(ApprovalExecutionCommandContext $command, ExecutableApprovalRelease $release, ApprovalExecutionState $execution, NodeEffect $effect): ApprovalExecutionPlan
    {
        $intent = $effect;
        if ($effect instanceof RequestApprovalEffect) {
            $configuration = $effect->configuration;
            $members = $this->assignees->resolveAssignees($configuration);
            $intent = new CreateApprovalTasks($members, $configuration->policy, $configuration->policyVersion);
        }
        $boundaries = $effect instanceof OpenScopeEffect
            ? $this->store->enclosingScopeBoundaries($command->instanceId, $execution->scopeId)
            : [];

        return $this->planner->enter($release->graph, $execution, $intent, $boundaries);
    }
}
