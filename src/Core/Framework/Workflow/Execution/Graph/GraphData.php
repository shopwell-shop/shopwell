<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Graph;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;

/**
 * @internal
 *
 * 契约图：`{nodes: [], edges: []}`。
 *
 * 三件职责，多一件都不要加：
 *   1. `fromArray()` 白名单解析（丢掉 UI 字段）；
 *   2. 邻接索引（`outgoing`），路由时不再线性扫全部边；
 *   3. 少量结构查询（单一起点、节点查找）。
 *
 * **不可变**：解析完就是只读的。运行期要记录"跑到哪"，写在实例行
 * （`current_node_id` / `round`）里，不要往图里塞状态——图是模板的快照，
 * 一份图被 N 个实例共享。
 *
 * 解析刻意**容忍**重复 id、悬空边这些结构问题，把它们留给校验器报成
 * 用户能看懂的 violation；`fromArray()` 只拒绝"根本不是一张图"的输入。
 * 如果这里就抛异常，保存接口只能返回 500，用户看不到问题在哪。
 */
#[Package('framework')]
final class GraphData
{
    /**
     * @var list<GraphNode> 保留原始顺序，含重复 id（供校验器报错）
     */
    private readonly array $nodeList;

    /**
     * @var array<string, GraphNode> 重复 id 时取第一个
     */
    private array $nodes = [];

    /**
     * @var array<string, list<GraphEdge>> 保持 JSON 里的顺序，路由靠它
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
            throw WorkflowExecutionException::invalidGraph('graphData 必须包含 nodes 与 edges 两个数组');
        }

        $nodes = [];

        foreach ($rawNodes as $index => $rawNode) {
            if (!\is_array($rawNode)) {
                throw WorkflowExecutionException::invalidGraph(\sprintf('nodes[%s] 不是对象', (string) $index));
            }

            $id = $rawNode['id'] ?? null;

            if (!\is_string($id) || $id === '') {
                throw WorkflowExecutionException::invalidGraph(\sprintf('nodes[%s].id 为空', (string) $index));
            }

            $type = $rawNode['type'] ?? null;

            if (!\is_string($type) || $type === '') {
                throw WorkflowExecutionException::invalidGraph(\sprintf('节点 %s 缺少 type', $id));
            }

            $properties = $rawNode['properties'] ?? [];

            if (!\is_array($properties)) {
                throw WorkflowExecutionException::invalidGraph(\sprintf('节点 %s 的 properties 不是对象', $id));
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
                throw WorkflowExecutionException::invalidGraph(\sprintf('edges[%s] 不是对象', (string) $index));
            }

            $id = $rawEdge['id'] ?? null;
            $source = $rawEdge['sourceNodeId'] ?? null;
            $target = $rawEdge['targetNodeId'] ?? null;

            if (!\is_string($id) || $id === '' || !\is_string($source) || $source === '' || !\is_string($target) || $target === '') {
                throw WorkflowExecutionException::invalidGraph(\sprintf('edges[%s] 缺少 id / sourceNodeId / targetNodeId', (string) $index));
            }

            $properties = $rawEdge['properties'] ?? [];

            if (!\is_array($properties)) {
                throw WorkflowExecutionException::invalidGraph(\sprintf('连线 %s 的 properties 不是对象', $id));
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
     * @return list<string> 出现超过一次的节点 id
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
     * 唯一的开始节点。
     *
     * 数量不对时直接抛：调用方（引擎）已经跑过校验，走到这里说明数据被改坏了，
     * 此时"随便挑一个开始"会让流程从错误的入口跑起来，比报错更糟。
     */
    public function singleStartNode(): GraphNode
    {
        $starts = $this->nodesOfType(WorkflowNodeTypes::START);

        if (\count($starts) !== 1) {
            throw WorkflowExecutionException::invalidGraph(\sprintf('开始节点必须有且仅有一个，当前 %d 个', \count($starts)));
        }

        return $starts[0];
    }

    /**
     * 回到契约形状，用于快照落库与 JSON 查看器。
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
