<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Definition;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Connection\WorkflowConnection;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;

/**
 * @internal A draft document is structurally decoded, not necessarily valid for publication or execution.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class WorkflowDocument
{
    final public const SCHEMA_VERSION = 1;

    /**
     * @param list<WorkflowNode> $nodes
     * @param list<WorkflowConnection> $connections
     */
    public function __construct(
        public array $nodes,
        public array $connections,
        public string $policy = 'core:structured',
    ) {
    }
}
