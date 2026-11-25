<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Command;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionStore;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalFlowExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalReleaseLoader;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalSerialProgressionTest
 */
#[Package('framework')]
class StartInstanceHandler implements ApprovalExecutionCommandHandler
{
    public function __construct(
        private readonly ApprovalExecutionStore $store,
        private readonly ApprovalFlowExecutor $executor,
        private readonly ApprovalReleaseLoader $releases,
    ) {
    }

    public function execute(ApprovalExecutionCommandContext $command): ApprovalInstanceStatus|ApprovalOutcome
    {
        $this->store->requireNotStarted($command->instanceId);
        $release = $this->releases->loadExecutable($command->releaseId);
        $this->store->createExecution($command->instanceId, $release->graph->startNodeId, null, null, null, 1, $command->context);
        $outcome = $this->executor->run($command, $release);
        if ($outcome !== null || !$this->store->hasTasks($command->instanceId)) {
            throw ApprovalFlowException::invalidGraphPlan('The initial route must reach an approval node before completing.');
        }

        return ApprovalInstanceStatus::Running;
    }
}
