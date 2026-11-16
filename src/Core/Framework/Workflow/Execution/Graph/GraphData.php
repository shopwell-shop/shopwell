<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Graph;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;

/**
 * Immutable contract graph with whitelisted parsing, ordered adjacency indexes and structural queries.
 * Parsing preserves duplicate node IDs and dangling edges for the validator to report.
 * nodeList retains every node in input order; node lookup uses the first occurrence of each ID.
 * Outgoing edges retain their JSON order because routing evaluates them in that order.
 *
 * @internal
 */
#[Package('framework')]
final class GraphData
{
    /**
     * @var list<GraphNode>
     */
    private readonly array $nodeList;

    /**
     * @var array<string, GraphNode>
     */
    private array $nodes = [];

    /**
     * @var array<string, list<GraphEdge>>
     */
    private array $outgoing = [];

    /**
     * @var list<GraphEdge>
     */
    private readonly array $edges;

    /**
     * @param list<GraphNode> $nodes
     * @param list<GraphEdge> $edges
     */
    private function __construct(array $nodes, array $edges)
    {
        $this->nodeList = $nodes;
        $this->edges = $edges;

        foreach ($nodes as $node) {
            $this->nodes[$node->id] ??= $node;
        }

        foreach ($edges as $edge) {
            $this->outgoing[$edge->sourceNodeId][] = $edge;
        }
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        $rawNodes = $raw['nodes'] ?? null;
        $rawEdges = $raw['edges'] ?? null;

        if (!\is_array($rawNodes) || !\is_array($rawEdges)) {
            throw WorkflowExecutionException::invalidGraph('graphData must contain nodes and edges arrays.');
        }

        $nodes = [];

        foreach ($rawNodes as $index => $rawNode) {
            if (!\is_array($rawNode)) {
                throw WorkflowExecutionException::invalidGraph(\sprintf('nodes[%s] must be an object.', (string) $index));
            }

            $id = $rawNode['id'] ?? null;

            if (!\is_string($id) || $id === '') {
                throw WorkflowExecutionException::invalidGraph(\sprintf('nodes[%s].id must not be empty.', (string) $index));
            }

            $type = $rawNode['type'] ?? null;

            if (!\is_string($type) || $type === '') {
                throw WorkflowExecutionException::invalidGraph(\sprintf('Node %s is missing type.', $id));
            }

            $properties = $rawNode['properties'] ?? [];

            if (!\is_array($properties)) {
                throw WorkflowExecutionException::invalidGraph(\sprintf('Node %s properties must be an object.', $id));
            }

            $nodes[] = new GraphNode(
                $id,
                $type,
                (float) ($rawNode['x'] ?? 0),
                (float) ($rawNode['y'] ?? 0),
                $properties,
            );
        }

        $edges = [];

        foreach ($rawEdges as $index => $rawEdge) {
            if (!\is_array($rawEdge)) {
                throw WorkflowExecutionException::invalidGraph(\sprintf('edges[%s] must be an object.', (string) $index));
            }

            $id = $rawEdge['id'] ?? null;
            $source = $rawEdge['sourceNodeId'] ?? null;
            $target = $rawEdge['targetNodeId'] ?? null;

            if (!\is_string($id) || $id === '' || !\is_string($source) || $source === '' || !\is_string($target) || $target === '') {
                throw WorkflowExecutionException::invalidGraph(\sprintf('edges[%s] is missing id / sourceNodeId / targetNodeId.', (string) $index));
            }

            $properties = $rawEdge['properties'] ?? [];

            if (!\is_array($properties)) {
                throw WorkflowExecutionException::invalidGraph(\sprintf('Edge %s properties must be an object.', $id));
            }

            $edges[] = new GraphEdge($id, $source, $target, $properties);
        }

        return new self($nodes, $edges);
    }

    /**
     * @return list<GraphNode>
     */
    public function nodeList(): array
    {
        return $this->nodeList;
    }

    /**
     * @return list<GraphEdge>
     */
    public function edges(): array
    {
        return $this->edges;
    }

    public function hasNode(string $nodeId): bool
    {
        return isset($this->nodes[$nodeId]);
    }

    public function node(string $nodeId): GraphNode
    {
        return $this->nodes[$nodeId] ?? throw WorkflowExecutionException::unknownNode($nodeId);
    }

    /**
     * @return list<GraphEdge>
     */
    public function outgoing(string $nodeId): array
    {
        return $this->outgoing[$nodeId] ?? [];
    }

    /**
     * @return list<string>
     */
    public function duplicateNodeIds(): array
    {
        $seen = [];
        $duplicated = [];

        foreach ($this->nodeList as $node) {
            if (isset($seen[$node->id])) {
                $duplicated[$node->id] = true;

                continue;
            }

            $seen[$node->id] = true;
        }

        return array_keys($duplicated);
    }

    /**
     * @return list<GraphNode>
     */
    public function nodesOfType(string $type): array
    {
        return array_values(array_filter(
            $this->nodeList,
            static fn (GraphNode $node): bool => $node->type === $type,
        ));
    }

    /**
     * Return the unique start node; an invalid count indicates corrupted or unvalidated data.
     */
    public function singleStartNode(): GraphNode
    {
        $starts = $this->nodesOfType(WorkflowNodeTypes::START);

        if (\count($starts) !== 1) {
            throw WorkflowExecutionException::invalidGraph(\sprintf('Exactly one start node is required; found %d.', \count($starts)));
        }

        return $starts[0];
    }

    /**
     * Serialize the contract shape for snapshots and JSON output.
     *
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'nodes' => array_map(static fn (GraphNode $node): array => $node->toArray(), $this->nodeList),
            'edges' => array_map(static fn (GraphEdge $edge): array => $edge->toArray(), $this->edges),
        ];
    }
}
