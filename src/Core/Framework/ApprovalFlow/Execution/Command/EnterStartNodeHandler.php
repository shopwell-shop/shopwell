<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Command;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionPreparation;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionStore;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalFlowExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalNodeEntry;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalReleaseLoader;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\AdvanceEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalNodeProgressionTest
 */
#[Package('framework')]
class EnterStartNodeHandler implements ApprovalExecutionCommandHandler
{
    public function __construct(
        private readonly ApprovalExecutionStore $store,
        private readonly ApprovalFlowExecutor $executor,
        private readonly ApprovalNodeEntry $nodes,
        private readonly ApprovalReleaseLoader $releases,
        private readonly ApprovalExecutionPreparation $preparation,
    ) {
    }

    public function execute(ApprovalExecutionCommandContext $command): ApprovalInstanceStatus|ApprovalOutcome
    {
        $this->store->requireNotStarted($command->instanceId);
        $release = $this->releases->loadExecutable($command->releaseId);
        $graph = $release->graph;
        $graph->startRoute();
        $effect = $this->nodes->enter($release, $graph->startNodeId);
        if (!$effect instanceof AdvanceEffect) {
            throw ApprovalFlowException::invalidGraphPlan('The start node must advance to its successor.');
        }

        $id = $this->store->createExecution($command->instanceId, $graph->startNodeId, null, null, null, 1, $command->context);
        $execution = $this->store->execution($command->instanceId, $id);
        $plan = $this->preparation->fromEffect($command, $release, $execution, $effect);
        $this->executor->execute($command, $plan);

        return ApprovalInstanceStatus::Running;
    }
}
