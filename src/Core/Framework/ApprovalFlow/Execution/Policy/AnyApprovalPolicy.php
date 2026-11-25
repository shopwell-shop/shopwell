<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Policy;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskStatus;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AnyApprovalPolicy implements ApprovalPolicyHandler
{
    public function getType(): string
    {
        return 'core:any';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function evaluate(array $statuses): ?ApprovalOutcome
    {
        if (\in_array(ApprovalTaskStatus::Rejected, $statuses, true)) {
            return ApprovalOutcome::Rejected;
        }

        return \in_array(ApprovalTaskStatus::Approved, $statuses, true) ? ApprovalOutcome::Approved : null;
    }
}
