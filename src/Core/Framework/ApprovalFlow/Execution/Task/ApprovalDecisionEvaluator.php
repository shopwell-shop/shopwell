<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Task;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalPolicy;
use Shopwell\Core\Framework\Log\Package;

/**
 * Aggregates the closed, fixed-assignee group with the published veto rule.
 *
 * @internal
 */
#[Package('framework')]
class ApprovalDecisionEvaluator
{
    /**
     * @param non-empty-list<ApprovalTaskStatus> $statuses
     */
    public function evaluate(ApprovalPolicy $policy, array $statuses): ?ApprovalOutcome
    {
        if (\in_array(ApprovalTaskStatus::Rejected, $statuses, true)) {
            return ApprovalOutcome::Rejected;
        }
        if ($policy === ApprovalPolicy::Any && \in_array(ApprovalTaskStatus::Approved, $statuses, true)) {
            return ApprovalOutcome::Approved;
        }
        foreach ($statuses as $status) {
            if ($status !== ApprovalTaskStatus::Approved) {
                return null;
            }
        }

        return ApprovalOutcome::Approved;
    }
}
