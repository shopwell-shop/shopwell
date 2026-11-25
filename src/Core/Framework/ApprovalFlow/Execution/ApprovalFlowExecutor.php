<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Command\ApprovalExecutionCommandContext;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Plan\ApprovalExecutionPlan;
use Shopwell\Core\Framework\Log\Package;

/**
 * Applies a prepared plan and drives ready executions to their next durable boundaries.
 * Commands choose admission and continuation; preparation, planning and storage own their phases.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalParallelExecutionTest
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalSerialProgressionTest
 */
#[Package('framework')]
class ApprovalFlowExecutor
{
    public function __construct(
        private readonly ApprovalExecutionStore $store,
        private readonly ApprovalExecutionPreparation $preparation,
    ) {
    }

    public function execute(ApprovalExecutionCommandContext $command, ApprovalExecutionPlan $plan): ?ApprovalOutcome
    {
        return $this->store->apply($command->instanceId, $plan, $command->commandId, $command->context);
    }

    public function run(ApprovalExecutionCommandContext $command, ExecutableApprovalRelease $release): ?ApprovalOutcome
    {
        while (true) {
            $execution = $this->store->nextReady($command->instanceId);
            if ($execution === null) {
                return null;
            }
            $plan = $this->preparation->enter($command, $release, $execution);
            $outcome = $this->execute($command, $plan);
            if ($outcome !== null) {
                return $outcome;
            }
        }
    }
}
