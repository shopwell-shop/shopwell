<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\Log\Package;

/**
 * The control-flow index and frozen node inputs verified for the current command.
 *
 * @phpstan-type NodeInput array{type: string, version: string, authoredJson: string, compiledJson: string, index: int}
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\ApprovalNodeEntryTest
 */
#[Package('framework')]
final readonly class ExecutableApprovalRelease
{
    /**
     * @param array<string, NodeInput> $nodes
     */
    public function __construct(public ApprovalExecutionGraph $graph, private array $nodes)
    {
    }

    /**
     * @return NodeInput
     */
    public function node(string $nodeId): array
    {
        return $this->nodes[$nodeId] ?? throw ApprovalFlowException::releaseNodeNotFound($this->graph->releaseId, $nodeId);
    }
}
