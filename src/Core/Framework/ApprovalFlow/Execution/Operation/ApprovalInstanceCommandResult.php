<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Operation;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\Log\Package;

/**
 * The committed command's response, which may precede the instance's current version on a retry.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class ApprovalInstanceCommandResult
{
    public function __construct(
        public string $instanceId,
        public string $commandId,
        public ApprovalInstanceStatus $status,
        public int $aggregateVersion,
        public ?ApprovalOutcome $outcome = null,
    ) {
    }
}
