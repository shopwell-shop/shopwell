<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Executor;

use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\AdvanceEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\CompleteEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\RequestApprovalEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\NodeExecutionContext;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\Log\Package;

/**
 * Pure node entry: return a controlled intent without reading business state, writing storage or sending requests.
 * Configuration comes from the compiler for the same exact node identity; each executor checks its expected type.
 * Effects have no execution authority. The runtime binds them to the current execution and validates them before commit.
 */
#[Package('framework')]
interface NodeExecutor
{
    public function getType(): string;

    /**
     * Exact supported node version, not a deployment artifact identifier.
     */
    public function getVersion(): string;

    public function enter(NodeConfiguration $configuration, NodeExecutionContext $context = new NodeExecutionContext()): AdvanceEffect|RequestApprovalEffect|CompleteEffect;
}
