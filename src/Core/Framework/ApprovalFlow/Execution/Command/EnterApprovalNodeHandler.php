<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Command;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionPreparation;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionStore;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalFlowExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalNodeEntry;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalReleaseLoader;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\RequestApprovalEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionStatus;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalNodeProgressionTest
 */
#[Package('framework')]
class EnterApprovalNodeHandler implements ApprovalExecutionCommandHandler
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
            throw ApprovalFlowException::invalidApprovalEntry($id, 'The current status is ' . $execution->status->value . '.');
        }
        $this->store->requireActive($command->instanceId, $execution);
        $facts = $this->store->nodeContext($command->instanceId, $execution);
        $release = $this->releases->loadExecutable($command->releaseId);
        $effect = $this->nodes->enter($release, $execution->nodeId, $facts);
        if (!$effect instanceof RequestApprovalEffect) {
            throw ApprovalFlowException::invalidApprovalEntry($id, 'The node did not request approval.');
        }
        $plan = $this->preparation->fromEffect($command, $release, $execution, $effect);
        $this->executor->execute($command, $plan);

        return ApprovalInstanceStatus::Running;
    }
}
