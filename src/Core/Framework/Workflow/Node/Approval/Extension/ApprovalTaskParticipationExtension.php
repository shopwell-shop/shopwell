<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Extension;

use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;

/**
 * Replace or observe a participant operation after ownership, round and target validation.
 * The instance is locked and writes share the approval transaction.
 * Queue external side effects through WorkflowEventQueue until commit.
 * The result is the newly created task ID.
 *
 * @extends Extension<string>
 */
#[Package('framework')]
final class ApprovalTaskParticipationExtension extends Extension
{
    public const NAME = 'workflow.approval.task.participation';

    /**
     * @internal
     */
    public function __construct(
        public readonly WorkflowTaskEntity $task,
        public readonly string $action,
        public readonly string $targetUserId,
        public readonly string $comment,
        public readonly WorkflowExecutionContext $execution,
    ) {
    }
}
