<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Result;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\Log\Package;

/**
 * Immutable core completion facts; business submission identity is not installed yet.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class ApprovalResult
{
    public function __construct(
        public string $id,
        public string $instanceId,
        public string $releaseId,
        public string $commandId,
        public int $aggregateVersion,
        public ApprovalOutcome $outcome,
        public \DateTimeImmutable $finishedAt,
    ) {
    }
}
