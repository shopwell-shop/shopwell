<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionStatus;
use Shopwell\Core\Framework\Log\Package;

/**
 * Current locked facts for one execution, detached from persistence and planning.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class ApprovalExecutionState
{
    public function __construct(
        public string $id,
        public string $nodeId,
        public ApprovalNodeExecutionStatus $status,
        public ?string $scopeId = null,
        public ?string $branchId = null,
        public int $generation = 1,
        public bool $advanced = false,
        public int $sequence = 1,
        public ?string $openedScopeId = null,
        public ?string $waitingScopeId = null,
        public ?ApprovalOutcome $outcome = null,
        public ?string $approvalPolicy = null,
        public ?string $approvalPolicyVersion = null,
    ) {
    }
}
