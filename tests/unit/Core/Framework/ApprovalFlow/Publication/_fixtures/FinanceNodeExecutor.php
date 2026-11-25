<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\CompleteEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\NodeExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\NodeExecutionContext;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class FinanceNodeExecutor implements NodeExecutor
{
    public function getType(): string
    {
        return 'finance:review';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function enter(NodeConfiguration $configuration, NodeExecutionContext $context = new NodeExecutionContext()): CompleteEffect
    {
        if (!$configuration instanceof FinanceNodeConfiguration) {
            throw new \RuntimeException('Expected finance configuration.');
        }

        return new CompleteEffect($configuration->enabled ? ApprovalOutcome::Approved : ApprovalOutcome::Rejected);
    }
}
