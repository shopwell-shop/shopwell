<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\ApprovalAssignment;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalMode;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class CompiledApprovalConfiguration
{
    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(
        public ApprovalAssignment $assignment,
        public ApprovalMode $mode,
        public array $configuration,
    ) {
    }
}
