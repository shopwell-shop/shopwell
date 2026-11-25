<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Policy;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskStatus;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AllApprovalPolicy implements ApprovalPolicyHandler
{
    public function getType(): string
    {
        return 'core:all';
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
        foreach ($statuses as $status) {
            if ($status !== ApprovalTaskStatus::Approved) {
                return null;
            }
        }

        return ApprovalOutcome::Approved;
    }
}
