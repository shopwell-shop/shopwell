<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Command;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionStore;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalFlowExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalReleaseLoader;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalTransitionPlanner;
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
class AdvanceApprovalHandler implements ApprovalExecutionCommandHandler
{
    public function __construct(
        private readonly ApprovalExecutionStore $store,
        private readonly ApprovalFlowExecutor $executor,
        private readonly ApprovalReleaseLoader $releases,
        private readonly ApprovalTransitionPlanner $planner,
        private readonly bool $drainReady,
    ) {
    }

    public function execute(ApprovalExecutionCommandContext $command): ApprovalInstanceStatus|ApprovalOutcome
    {
        $execution = $this->store->completedApproval($command->instanceId, $command->requireExecutionId());
        if ($this->store->hasRejectedApproval($command->instanceId)) {
            $this->executor->execute($command, $this->planner->reject($execution));

            return ApprovalOutcome::Rejected;
        }

        $release = $this->releases->loadExecutable($command->releaseId);
        $plan = $this->planner->advance($release->graph, $execution);
        $this->executor->execute($command, $plan);

        return ($this->drainReady ? $this->executor->run($command, $release) : null) ?? ApprovalInstanceStatus::Running;
    }
}
