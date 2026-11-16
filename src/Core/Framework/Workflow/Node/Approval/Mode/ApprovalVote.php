<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Mode;

use Shopwell\Core\Framework\Log\Package;

/**
 * An immutable task projection; canceled and skipped tasks are excluded before aggregation.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class ApprovalVote
{
    public function __construct(
        public string $assigneeId,
        public string $state,
        public int $sequence,
    ) {
    }
}
