<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Command;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\Log\Package;

/**
 * Command-specific admission and continuation policy inside InstanceStore's existing transaction.
 * Implementations use the shared plan/executor; they do not own a second runtime or transaction.
 *
 * @internal
 */
#[Package('framework')]
interface ApprovalExecutionCommandHandler
{
    public function execute(ApprovalExecutionCommandContext $command): ApprovalInstanceStatus|ApprovalOutcome;
}
