<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Scope;

use Shopwell\Core\Framework\ApprovalFlow\Execution\Branch\ApprovalExecutionBranchStatus;
use Shopwell\Core\Framework\Log\Package;

/**
 * Runtime-owned members and arrival facts for this exact scope generation.
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalParallelExecutionTest
 */
#[Package('framework')]
final readonly class ApprovalScopeSnapshot
{
    /**
     * @var array<string, ApprovalExecutionBranchStatus>
     */
    public array $branches;

    /**
     * @param array<string, ApprovalExecutionBranchStatus> $branches
     */
    public function __construct(public string $id, public int $generation, array $branches)
    {
        $this->branches = array_map(static fn (ApprovalExecutionBranchStatus $status): ApprovalExecutionBranchStatus => $status, $branches);
    }
}
