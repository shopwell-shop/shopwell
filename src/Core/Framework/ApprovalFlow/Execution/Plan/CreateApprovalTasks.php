<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Plan;

use Shopwell\Core\Framework\Log\Package;

/**
 * Resolved members and the frozen policy; persistence does not resolve people again.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class CreateApprovalTasks implements ExecutionChange
{
    /**
     * @var non-empty-list<string>
     */
    public array $assigneeIds;

    /**
     * @param non-empty-list<string> $assigneeIds
     */
    public function __construct(array $assigneeIds, public string $policy, public string $policyVersion)
    {
        $this->assigneeIds = array_map(static fn (string $id): string => $id, $assigneeIds);
    }
}
