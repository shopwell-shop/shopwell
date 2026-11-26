<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Task;

use Shopwell\Core\Framework\Log\Package;

/**
 * Built-in reason identifiers; additional task actions may record their own namespaced reason.
 */
#[Package('framework')]
final class ApprovalTaskCompletionReason
{
    public const DECISION = 'core:decision';
    public const GROUP_RESOLVED = 'core:group-resolved';
    public const SCOPE_RESOLVED = 'core:scope-resolved';
    public const PROCESS_REJECTED = 'core:process-rejected';
    public const RETURNED = 'core:returned';
    public const GROUP_RETURNED = 'core:group-returned';
    public const REQUEST_WITHDRAWN = 'core:request-withdrawn';
}
