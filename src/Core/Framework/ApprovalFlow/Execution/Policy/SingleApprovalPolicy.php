<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Policy;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskStatus;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class SingleApprovalPolicy implements ApprovalPolicyHandler
{
    public function getType(): string
    {
        return 'core:single';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function evaluate(array $statuses): ?ApprovalOutcome
    {
        return match ($statuses[0]) {
            ApprovalTaskStatus::Approved => ApprovalOutcome::Approved,
            ApprovalTaskStatus::Rejected => ApprovalOutcome::Rejected,
            default => null,
        };
    }
}
