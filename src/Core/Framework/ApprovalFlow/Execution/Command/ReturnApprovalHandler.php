<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Command;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionPreparation;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionState;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionStore;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalFlowExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalReleaseLoader;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Event\ApprovalReturnTargetsEvent;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskDecisionStore;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalParallelExecutionTest
 *
 * @phpstan-import-type Target from ApprovalReturnTargetsEvent
 */
#[Package('framework')]
class ReturnApprovalHandler
{
    public function __construct(
        private readonly ApprovalTaskDecisionStore $tasks,
        private readonly ApprovalExecutionStore $executions,
        private readonly ApprovalReleaseLoader $releases,
        private readonly ApprovalExecutionPreparation $preparation,
        private readonly ApprovalFlowExecutor $executor,
        private readonly EventDispatcherInterface $events,
    ) {
    }

    /**
     * @return list<Target>
     */
    public function targets(string $instanceId, string $releaseId, string $taskId, Context $context): array
    {
        $source = $this->tasks->pendingExecution($instanceId, $taskId);

        return $this->eligibleTargets($instanceId, $releaseId, $taskId, $source, $context);
    }

    public function execute(ApprovalExecutionCommandContext $command, string $taskId, string $targetExecutionId): ApprovalInstanceStatus
    {
        $source = $this->tasks->pendingExecution($command->instanceId, $taskId);
        foreach ($this->eligibleTargets($command->instanceId, $command->releaseId, $taskId, $source, $command->context) as $target) {
            if ($target['executionId'] !== $targetExecutionId) {
                continue;
            }

            $release = $this->releases->loadExecutable($command->releaseId);
            $plan = $this->preparation->returnToApproval($command, $release, $source, $taskId, $target);
            $this->executor->execute($command, $plan);

            return ApprovalInstanceStatus::Running;
        }

        throw ApprovalFlowException::returnTargetUnavailable($targetExecutionId);
    }

    /**
     * @return list<Target>
     */
    private function eligibleTargets(string $instanceId, string $releaseId, string $taskId, ApprovalExecutionState $source, Context $context): array
    {
        if ($this->executions->hasRejectedApproval($instanceId)) {
            return [];
        }

        $event = new ApprovalReturnTargetsEvent(
            $instanceId,
            $releaseId,
            $taskId,
            $source->nodeId,
            $context,
            $this->executions->returnTargets($instanceId, $source),
        );
        $this->events->dispatch($event);

        return $event->getTargets();
    }
}
