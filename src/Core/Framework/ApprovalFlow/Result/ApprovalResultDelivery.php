<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Result;

use Shopwell\Core\Framework\Log\Package;

/**
 * A committed delivery lease; attempt fences confirmations from an earlier worker.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class ApprovalResultDelivery
{
    public function __construct(
        public ApprovalResult $result,
        public int $attempt,
        public \DateTimeImmutable $leaseUntil,
    ) {
    }
}
