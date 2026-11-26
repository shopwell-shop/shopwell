<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Command;

use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionStore;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class WithdrawInstanceHandler implements ApprovalExecutionCommandHandler
{
    public function __construct(private readonly ApprovalExecutionStore $store)
    {
    }

    public function execute(ApprovalExecutionCommandContext $command): ApprovalInstanceStatus
    {
        $this->store->withdraw($command->instanceId, $command->commandId, $command->context);

        return ApprovalInstanceStatus::Withdrawn;
    }
}
