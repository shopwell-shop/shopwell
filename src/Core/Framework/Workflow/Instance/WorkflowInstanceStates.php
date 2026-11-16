<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Instance;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;

/**
 * Instances transition only from running through WorkflowInstanceStateHandler; terminal states cannot change again.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowInstanceStates
{
    public const STATE_MACHINE = 'workflow_instance.state';

    /**
     * Running.
     */
    public const RUNNING = 'running';
    public const COMPLETED = 'completed';
    public const ACTION_COMPLETE = 'complete';

    /**
     * Approved after reaching the end through an approval path.
     */
    public const AGREED = 'agreed';

    /**
     * Rejected by an assignee or after reaching the end through a rejection path.
     */
    public const REJECTED = 'rejected';

    /**
     * Canceled by the initiator through ApprovalWorkflowService::cancel.
     */
    public const CANCELED = 'canceled';

    public const ACTION_AGREE = 'agree';
    public const ACTION_REJECT = 'reject';
    public const ACTION_CANCEL = 'cancel';

    /**
     * Map stored state names to the public uppercase status contract.
     */
    public const CONTRACT_STATUS = [
        self::RUNNING => 'RUNNING',
        self::COMPLETED => 'COMPLETED',
        self::AGREED => 'AGREE',
        self::REJECTED => 'REJECT',
        self::CANCELED => 'CANCEL',
    ];
}
