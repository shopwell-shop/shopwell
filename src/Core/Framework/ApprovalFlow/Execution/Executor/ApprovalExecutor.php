<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Executor;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\RequestApprovalEffect;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\CoreNodeType;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalNodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalExecutor implements NodeExecutor
{
    public function getType(): string
    {
        return CoreNodeType::APPROVAL;
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function enter(NodeConfiguration $configuration): RequestApprovalEffect
    {
        if (!$configuration instanceof ApprovalNodeConfiguration) {
            throw ApprovalFlowException::invalidNodeExecutor(self::class, 'Expected ApprovalNodeConfiguration.');
        }

        return new RequestApprovalEffect($configuration);
    }
}
