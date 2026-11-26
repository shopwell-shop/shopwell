<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Plan;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class ReturnToApproval implements ExecutionChange
{
    public function __construct(
        public string $taskId,
        public string $targetExecutionId,
        public string $nodeId,
        public CreateApprovalTasks $tasks,
    ) {
    }
}
