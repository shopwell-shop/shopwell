<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\Log\Package;

/**
 * Readonly control-flow indexes copied from a verified release, without configuration or persistence.
 *
 * @phpstan-import-type GraphPayload from GraphDiagnostics
 *
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalExecutionGraph
{
    /**
     * @var array<string, true>
     */
    private array $nodes;

    /**
     * @var array<string, non-empty-list<array{connectionId: string, nextNodeId: string, sourcePort: string}>>
     */
    private array $outgoing;

    /**
     * @param GraphPayload $graph
     */
    public function __construct(public string $releaseId, public string $startNodeId, public string $endNodeId, \stdClass $graph)
    {
        $nodes = [];
        foreach ($graph->nodes as $node) {
            $nodes[$node->id] = true;
        }
        $outgoing = [];
        foreach ($graph->connections as $connection) {
            $outgoing[$connection->source][] = [
                'connectionId' => $connection->id,
                'nextNodeId' => $connection->target,
                'sourcePort' => $connection->sourcePort,
            ];
        }
        $this->nodes = $nodes;
        $this->outgoing = $outgoing;
    }

    /**
     * @return array{connectionId: string, nextNodeId: string}
     */
    public function startRoute(): array
    {
        $routes = $this->outgoing[$this->startNodeId] ?? [];
        if (\count($routes) !== 1) {
            throw ApprovalFlowException::unsupportedStartRoute($this->releaseId);
        }

        return ['connectionId' => $routes[0]['connectionId'], 'nextNodeId' => $routes[0]['nextNodeId']];
    }

    /**
     * @param list<string>|null $outputPorts
     *
     * @return non-empty-list<array{connectionId: string, nextNodeId: string}>
     */
    public function successors(string $nodeId, ?array $outputPorts = null): array
    {
        $routes = [];
        foreach ($this->outgoing[$nodeId] ?? [] as $connection) {
            if ($outputPorts === null || \in_array($connection['sourcePort'], $outputPorts, true)) {
                $routes[] = ['connectionId' => $connection['connectionId'], 'nextNodeId' => $connection['nextNodeId']];
            }
        }
        if ($routes === [] || ($outputPorts === null && \count($routes) !== 1)) {
            throw ApprovalFlowException::unsupportedNodeRoute($this->releaseId, $nodeId);
        }

        return $routes;
    }

    /**
     * Every selected path returns inside its region, before any enclosing boundary or process end.
     *
     * @param non-empty-list<array{connectionId: string, nextNodeId: string}> $routes
     * @param list<string> $ancestorBoundaries
     */
    public function validateScope(string $nodeId, string $joinNodeId, array $routes, array $ancestorBoundaries): void
    {
        if (!isset($this->nodes[$joinNodeId]) || $joinNodeId === $nodeId || \in_array($joinNodeId, $ancestorBoundaries, true)) {
            throw ApprovalFlowException::invalidExecutionScope($nodeId, 'A distinct return boundary inside the current region is required.');
        }
        $occupied = [];
        foreach ($routes as $route) {
            $pending = [$route['nextNodeId']];
            $visited = [];
            while ($pending !== []) {
                $current = array_pop($pending);
                if ($current === $joinNodeId || isset($visited[$current])) {
                    continue;
                }
                if (isset($occupied[$current]) || \in_array($current, $ancestorBoundaries, true) || !isset($this->outgoing[$current])) {
                    throw ApprovalFlowException::invalidExecutionScope($nodeId, 'Selected branches must be disjoint and return before an enclosing boundary or process end.');
                }
                $visited[$current] = true;
                foreach ($this->outgoing[$current] as $connection) {
                    $pending[] = $connection['nextNodeId'];
                }
            }
            $occupied += $visited;
        }
    }
}
