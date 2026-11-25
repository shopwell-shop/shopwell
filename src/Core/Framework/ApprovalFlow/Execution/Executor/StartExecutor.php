<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Executor;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\AdvanceEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\NodeExecutionContext;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\CoreNodeType;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\StartNodeConfiguration;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class StartExecutor implements NodeExecutor
{
    public function getType(): string
    {
        return CoreNodeType::START;
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function enter(NodeConfiguration $configuration, NodeExecutionContext $context = new NodeExecutionContext()): AdvanceEffect
    {
        if (!$configuration instanceof StartNodeConfiguration) {
            throw ApprovalFlowException::invalidNodeExecutor(self::class, 'Expected StartNodeConfiguration.');
        }

        return new AdvanceEffect();
    }
}
