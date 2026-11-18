<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Assignee;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class ApprovalAssigneeResolution
{
    /**
     * @param list<string> $assigneeIds
     */
    public function __construct(public array $assigneeIds, public ?string $automaticDecision = null)
    {
    }
}
