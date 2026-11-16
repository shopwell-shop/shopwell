<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Mode;

use Shopwell\Core\Framework\Log\Package;

/**
 * The complete counted votes for one node and round, including pending decisions.
 * Expected total includes participants whose sequential tasks have not been created yet.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class ApprovalVotes
{
    /**
     * @param list<ApprovalVote> $votes
     */
    public function __construct(
        public array $votes,
        public int $agreed,
        public int $rejected,
        public int $total,
        public ?int $expectedTotal,
    ) {
    }
}
