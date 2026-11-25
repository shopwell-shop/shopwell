<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Command;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionPreparation;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionStore;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalFlowExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalNodeEntry;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalReleaseLoader;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\CompleteEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionStatus;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalCompletionTest
 */
#[Package('framework')]
class EnterEndNodeHandler implements ApprovalExecutionCommandHandler
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
        $id = $command->requireExecutionId();
        $execution = $this->store->execution($command->instanceId, $id);
        if ($execution->status !== ApprovalNodeExecutionStatus::Waiting) {
            throw ApprovalFlowException::invalidNodeProgression($id, 'The end execution must be waiting.');
        }
        $this->store->requireActive($command->instanceId, $execution);
        $release = $this->releases->loadExecutable($command->releaseId);
        if ($execution->nodeId !== $release->graph->endNodeId) {
            throw ApprovalFlowException::invalidGraphPlan('Only the published end boundary may complete the process.');
        }
        $effect = $this->nodes->enter($release, $execution->nodeId);
        if (!$effect instanceof CompleteEffect) {
            throw ApprovalFlowException::invalidGraphPlan('The end boundary must provide a process outcome.');
        }
        $plan = $this->preparation->fromEffect($command, $release, $execution, $effect);
        $this->executor->execute($command, $plan);

        return $effect->outcome;
    }
}
