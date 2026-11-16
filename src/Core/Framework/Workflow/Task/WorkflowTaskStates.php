<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Task;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignCalculator;

/**
 * Tasks transition only from todo through WorkflowTaskStateHandler; invalid repeated transitions protect against duplicate decisions.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowTaskStates
{
    public const STATE_MACHINE = 'workflow_task.state';

    /**
     * Pending.
     */
    public const TODO = 'todo';

    /**
     * Approved.
     */
    public const AGREED = 'agreed';

    /**
     * Rejected.
     */
    public const REJECTED = 'rejected';

    /**
     * Canceled after a skipped node, canceled instance or withdrawn approval step.
     */
    public const CANCELED = 'canceled';

    public const ACTION_AGREE = 'agree';
    public const ACTION_REJECT = 'reject';
    public const ACTION_CANCEL = 'cancel';

    /**
     * States counted by CounterSignCalculator; canceled tasks must not contribute to the denominator.
     */
    public const COUNTED_STATES = [self::TODO, self::AGREED, self::REJECTED];

    /**
     * Terminal states with no further actions.
     */
    public const FINAL_STATES = [self::AGREED, self::REJECTED, self::CANCELED];

    /**
     * Reserved states: FUTURE_SKIPPED, FUTURE_TRANSFERRED and FUTURE_DELEGATED; review COUNTED_STATES when adding them.
     */
    public const FUTURE_SKIPPED = 'skipped';
    public const FUTURE_TRANSFERRED = 'transferred';
    public const FUTURE_DELEGATED = 'delegated';
}
