<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Policy;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskStatus;
use Shopwell\Core\Framework\Log\Package;

/**
 * Computes a group result from frozen effective votes, without persistence or side effects.
 */
#[Package('framework')]
interface ApprovalPolicyHandler
{
    public function getType(): string;

    public function getVersion(): string;

    /**
     * @param non-empty-list<ApprovalTaskStatus> $statuses
     */
    public function evaluate(array $statuses): ?ApprovalOutcome;
}
