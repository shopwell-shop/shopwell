<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Executor;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\CompleteEffect;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\CoreNodeType;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\EndNodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class EndExecutor implements NodeExecutor
{
    public function getType(): string
    {
        return CoreNodeType::END;
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function enter(NodeConfiguration $configuration): CompleteEffect
    {
        if (!$configuration instanceof EndNodeConfiguration) {
            throw ApprovalFlowException::invalidNodeExecutor(self::class, 'Expected EndNodeConfiguration.');
        }

        return new CompleteEffect($configuration->outcome);
    }
}
