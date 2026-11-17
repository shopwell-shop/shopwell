<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Extension;

use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;

/**
 * Execute a declared plugin operation after locked ownership and policy checks.
 * Handle only the subscriber's own action, set result to true and stop propagation.
 * Throw on invalid input. Writes share the transaction; queue external effects until commit.
 * Core writes the operation audit after successful execution and never logs the input payload.
 *
 * @extends Extension<bool>
 */
#[Package('framework')]
final class ApprovalTaskOperationExtension extends Extension
{
    public const NAME = 'workflow.approval.task.operation';

    /**
     * @internal
     *
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly WorkflowTaskEntity $task,
        public readonly string $action,
        public readonly array $payload,
        public readonly string $comment,
        public readonly WorkflowExecutionContext $execution,
    ) {
    }
}
