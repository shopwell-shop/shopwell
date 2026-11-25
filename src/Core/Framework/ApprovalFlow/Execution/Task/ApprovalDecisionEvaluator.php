<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Task;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalReleaseLoader;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Policy\ApprovalPolicyRegistry;
use Shopwell\Core\Framework\Log\Package;

/**
 * Runs the retained policy and checks its result against the published veto contract.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalPolicyExtensionTest
 */
#[Package('framework')]
class ApprovalDecisionEvaluator
{
    public function __construct(
        private readonly ApprovalPolicyRegistry $policies,
        private readonly ApprovalReleaseLoader $releases,
    ) {
    }

    /**
     * @param non-empty-list<ApprovalTaskStatus> $statuses
     */
    public function evaluate(string $releaseId, string $type, string $version, array $statuses): ?ApprovalOutcome
    {
        $this->releases->load($releaseId);
        $outcome = $this->policies->get($type, $version)->evaluate($statuses);
        if (\in_array(ApprovalTaskStatus::Rejected, $statuses, true) && $outcome !== ApprovalOutcome::Rejected) {
            throw ApprovalFlowException::invalidApprovalPolicyResult($type, $version);
        }

        return $outcome;
    }
}
