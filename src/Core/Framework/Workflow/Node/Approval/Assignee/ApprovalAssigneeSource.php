<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Assignee;

use Shopwell\Core\Framework\Log\Package;

/**
 * Register with shopwell.workflow.approval.assignee_source.
 */
#[Package('framework')]
interface ApprovalAssigneeSource
{
    public function getName(): string;

    /**
     * Validate design-time configuration without resolving organizational membership.
     * Use an execution resolver identifier that remains stable for published snapshots.
     *
     * @param array<string, mixed> $configuration
     */
    public function compile(array $configuration): ApprovalAssignment;
}
