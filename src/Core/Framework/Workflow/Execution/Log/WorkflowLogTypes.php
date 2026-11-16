<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Log;

use Shopwell\Core\Framework\Log\Package;

/**
 * Log categories are separate from action names so extensions can distinguish returns, transfers and similar operations.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowLogTypes
{
    /**
     * Normal approval actions: start, agree, reject, cancel and copy.
     */
    public const NORMAL = 'normal';

    /**
     * Reserved categories: RETURN, ADD_SIGN, TRANSFER, DELEGATE, TIMEOUT and SYSTEM; log_type is a VARCHAR.
     */
    public const FUTURE_RETURN = 'return';
    public const FUTURE_ADD_SIGN = 'addSign';
    public const FUTURE_TRANSFER = 'transfer';
    public const FUTURE_DELEGATE = 'delegate';
    public const FUTURE_TIMEOUT = 'timeout';
    public const FUTURE_SYSTEM = 'system';
}
