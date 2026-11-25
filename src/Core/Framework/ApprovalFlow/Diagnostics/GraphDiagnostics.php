<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Diagnostics;

use Opis\JsonSchema\Exceptions\SchemaException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphSchemaValidator;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\CoreNodeType;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\Log\Package;

/**
 * @phpstan-type Port object{
 *   id: string,
 *   direction: 'input'|'output',
 *   channel: 'control',
 *   cardinality: 'one'|'many'
 * }&\stdClass
 * @phpstan-type Node object{
 *   id: string,
 *   type: string,
 *   typeVersion: string,
 *   config: \stdClass,
 *   ports: list<Port>,
 *   position: object{x: int|float, y: int|float}&\stdClass,
 *   extensions: \stdClass
 * }&\stdClass
 * @phpstan-type Connection object{
 *   id: string,
 *   source: string,
 *   target: string,
 *   sourcePort: string,
 *   targetPort: string,
 *   condition: object{kind: string}&\stdClass,
 *   extensions: \stdClass
 * }&\stdClass
 * @phpstan-type LayoutEntry object{
 *   nodeId: string,
 *   width: int|float,
 *   height: int|float,
 *   collapsed: bool
 * }&\stdClass
 * @phpstan-type GraphPayload object{
 *   schemaVersion: string,
 *   id: string,
 *   meta: object{name: string, editingMode: string, formSchemaRef: string}&\stdClass,
 *   nodes: list<Node>,
 *   connections: list<Connection>,
 *   layout: object{algorithm: string, nodes: list<LayoutEntry>}&\stdClass,
 *   extensions: \stdClass
 * }&\stdClass
 *
 * @internal
 */
#[Package('framework')]
class GraphDiagnostics
{
    public function __construct(
        private readonly NodeTypeRegistry $nodeTypes,
        private readonly GraphSchemaValidator $schemas,
        private readonly string $graphSchema,
        private readonly GraphJsonDecoder $jsonDecoder,
    ) {
    }

    public function analyze(DraftGraph $graph): GraphDiagnosticsReport
    {
        try {
            $payload = $this->jsonDecoder->decode($graph->json);
        } catch (GraphJsonException $exception) {
            return new GraphDiagnosticsReport([$exception->violation]);
        }

        return $this->analyzePayload($payload);
    }

    public function analyzePayload(mixed $payload): GraphDiagnosticsReport
    {
        $violations = $this->schemas->validate($payload, $this->graphSchema);
        if ($violations !== []) {
            return new GraphDiagnosticsReport($violations);
        }

        // The closed wire schema establishes this shape; no downstream check repairs or coerces a value.
        /** @var GraphPayload $payload */
        $nodeAnalysis = $this->analyzeNodes($payload->nodes);
        $connectionAnalysis = $this->analyzeConnections($payload->connections, $nodeAnalysis['nodes'], $nodeAnalysis['ports']);
        $violations = [
            ...$nodeAnalysis['violations'],
            ...$connectionAnalysis['violations'],
            ...$this->layoutViolations($payload->layout->nodes, $nodeAnalysis['nodes']),
            ...$this->extensionViolations($payload->extensions, '/extensions'),
        ];

        $report = new GraphDiagnosticsReport($violations);
        if (!$report->isWritable()) {
            return $report;
        }
        array_push($violations, ...$this->controlFlowViolations($payload->nodes, $connectionAnalysis['adjacency'], $connectionAnalysis['reverse']));

        return new GraphDiagnosticsReport($violations);
    }

    /**
     * @param list<Node> $graphNodes
     *
     * @return array{nodes: array<string, Node>, ports: array<string, array<string, Port>>, violations: list<GraphViolation>}
     */
    private function analyzeNodes(array $graphNodes): array
    {
        $violations = [];
        $nodes = [];
        $ports = [];
        $types = $this->nodeTypes->all();
        foreach ($graphNodes as $index => $node) {
            $path = '/nodes/' . $index;
            if (isset($nodes[$node->id])) {
                $violations[] = new GraphViolation(ViolationCategory::Structure, 'duplicate_node', $path . '/id', 'Duplicate node id ' . $node->id . '.');
                continue;
            }
            $nodes[$node->id] = $node;
            $specification = $types[$node->type . '@' . $node->typeVersion] ?? null;
            if ($specification === null) {
                $violations[] = new GraphViolation(ViolationCategory::Capability, 'missing_node_type', $path . '/type', 'Node type ' . $node->type . '@' . $node->typeVersion . ' is unavailable.');
            } else {
                try {
                    $invalidConfig = $this->schemas->validate($node->config, $specification->draftConfigurationSchema, $path . '/config');
                    array_push($violations, ...$invalidConfig);
                    if ($invalidConfig === [] && $specification->draftConfigurationSchema !== $specification->configurationSchema) {
                        array_push($violations, ...$this->schemas->validate($node->config, $specification->configurationSchema, $path . '/config', ViolationCategory::Draft));
                    }
                } catch (SchemaException $exception) {
                    throw ApprovalFlowException::invalidNodeType($specification->source, 'configuration schema cannot be evaluated', $exception);
                }
            }
            $nodePorts = [];
            foreach ($node->ports as $portIndex => $port) {
                $portPath = $path . '/ports/' . $portIndex;
                if (isset($nodePorts[$port->id])) {
                    $violations[] = new GraphViolation(ViolationCategory::Structure, 'duplicate_port', $portPath . '/id', 'Duplicate port id ' . $port->id . '.');
                }
                $nodePorts[$port->id] = $port;
                if ($specification !== null && ($specification->ports[$port->id] ?? null) !== $port->direction) {
                    $violations[] = new GraphViolation(ViolationCategory::Structure, 'invalid_port', $portPath, 'Port does not match the exact node declaration.');
                }
            }
            if ($specification !== null && array_diff(array_keys($specification->ports), array_keys($nodePorts)) !== []) {
                $violations[] = new GraphViolation(ViolationCategory::Structure, 'missing_port', $path . '/ports', 'Declared ports are missing.');
            }
            $ports[$node->id] = $nodePorts;
            array_push($violations, ...$this->extensionViolations($node->extensions, $path . '/extensions'));
        }

        return ['nodes' => $nodes, 'ports' => $ports, 'violations' => $violations];
    }

    /**
     * @param list<Connection> $connections
     * @param array<string, Node> $nodes
     * @param array<string, array<string, Port>> $ports
     *
     * @return array{adjacency: array<string, list<string>>, reverse: array<string, list<string>>, violations: list<GraphViolation>}
     */
    private function analyzeConnections(array $connections, array $nodes, array $ports): array
    {
        $violations = [];
        $connectionIds = [];
        $adjacency = [];
        $reverse = [];
        $portConnections = [];
        foreach ($connections as $index => $connection) {
            $path = '/connections/' . $index;
            if (isset($connectionIds[$connection->id])) {
                $violations[] = new GraphViolation(ViolationCategory::Structure, 'duplicate_connection', $path . '/id', 'Duplicate connection id ' . $connection->id . '.');
            }
            $connectionIds[$connection->id] = true;
            foreach (['source' => 'output', 'target' => 'input'] as $side => $direction) {
                $nodeId = $connection->{$side};
                $portId = $connection->{$side . 'Port'};
                if (!isset($nodes[$nodeId])) {
                    $violations[] = new GraphViolation(ViolationCategory::Structure, 'dangling_node', $path . '/' . $side, 'Connection references missing node ' . $nodeId . '.');
                    continue;
                }
                $port = $ports[$nodeId][$portId] ?? null;
                if ($port === null || $port->direction !== $direction) {
                    $violations[] = new GraphViolation(ViolationCategory::Structure, 'dangling_port', $path . '/' . $side . 'Port', 'Connection references a missing port or the wrong direction.');
                    continue;
                }
                $count = ($portConnections[$nodeId][$portId] ?? 0) + 1;
                $portConnections[$nodeId][$portId] = $count;
                if ($count > 1 && $port->cardinality === 'one') {
                    $violations[] = new GraphViolation(ViolationCategory::Draft, 'port_cardinality', $path . '/' . $side . 'Port', 'Port allows only one connection.');
                }
            }
            $adjacency[$connection->source][] = $connection->target;
            $reverse[$connection->target][] = $connection->source;
            if ($connection->condition->kind !== 'always') {
                $violations[] = new GraphViolation(ViolationCategory::Capability, 'missing_condition_capability', $path . '/condition', 'Conditional routing is not installed in this skeleton increment.');
            }
            array_push($violations, ...$this->extensionViolations($connection->extensions, $path . '/extensions'));
        }

        return ['adjacency' => $adjacency, 'reverse' => $reverse, 'violations' => $violations];
    }

    /**
     * @param list<LayoutEntry> $entries
     * @param array<string, Node> $nodes
     *
     * @return list<GraphViolation>
     */
    private function layoutViolations(array $entries, array $nodes): array
    {
        $violations = [];
        $layoutIds = [];
        foreach ($entries as $index => $entry) {
            $path = '/layout/nodes/' . $index . '/nodeId';
            if (!isset($nodes[$entry->nodeId]) || isset($layoutIds[$entry->nodeId])) {
                $violations[] = new GraphViolation(ViolationCategory::Structure, 'invalid_layout_reference', $path, 'Layout must reference an existing node exactly once.');
            }
            $layoutIds[$entry->nodeId] = true;
        }

        return $violations;
    }

    /**
     * @param list<Node> $nodes
     * @param array<string, list<string>> $adjacency
     * @param array<string, list<string>> $reverse
     *
     * @return list<GraphViolation>
     */
    private function controlFlowViolations(array $nodes, array $adjacency, array $reverse): array
    {
        $starts = [];
        $ends = [];
        foreach ($nodes as $node) {
            if ($node->type === CoreNodeType::START) {
                $starts[] = $node->id;
            }
            if ($node->type === CoreNodeType::END) {
                $ends[] = $node->id;
            }
        }
        $violations = [];
        if (\count($starts) !== 1) {
            $violations[] = new GraphViolation(ViolationCategory::Draft, 'start_count', '/nodes', 'A process needs exactly one start node.');
        }
        if (\count($ends) !== 1) {
            $violations[] = new GraphViolation(ViolationCategory::Draft, 'end_count', '/nodes', 'A process needs exactly one end node.');
        }
        $reachable = $this->reachable($starts, $adjacency);
        $terminating = $this->reachable($ends, $reverse);
        $incoming = [];
        foreach ($nodes as $index => $node) {
            if (!isset($reachable[$node->id])) {
                $violations[] = new GraphViolation(ViolationCategory::Draft, 'unreachable_node', '/nodes/' . $index, 'Node cannot be reached from the start.');
            }
            if (!isset($terminating[$node->id])) {
                $violations[] = new GraphViolation(ViolationCategory::Draft, 'non_terminating_node', '/nodes/' . $index, 'Node has no path to the end.');
            }
            $incoming[$node->id] = \count($reverse[$node->id] ?? []);
        }
        $ready = [];
        foreach ($incoming as $id => $count) {
            if ($count === 0) {
                $ready[] = $id;
            }
        }
        $visited = 0;
        for ($index = 0; $index < \count($ready); ++$index) {
            ++$visited;
            foreach ($adjacency[$ready[$index]] ?? [] as $target) {
                --$incoming[$target];
                if ($incoming[$target] === 0) {
                    $ready[] = $target;
                }
            }
        }
        if ($visited !== \count($nodes)) {
            $violations[] = new GraphViolation(ViolationCategory::Draft, 'cycle', '/connections', 'Arbitrary control-flow cycles are not supported.');
        }

        return $violations;
    }

    /**
     * @param list<string> $seeds
     * @param array<string, list<string>> $adjacency
     *
     * @return array<string, true>
     */
    private function reachable(array $seeds, array $adjacency): array
    {
        $seen = [];
        $pending = $seeds;
        while ($pending !== []) {
            $id = array_pop($pending);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            foreach ($adjacency[$id] ?? [] as $target) {
                if (!isset($seen[$target])) {
                    $pending[] = $target;
                }
            }
        }

        return $seen;
    }

    /**
     * @return list<GraphViolation>
     */
    private function extensionViolations(\stdClass $extensions, string $path): array
    {
        $violations = [];
        foreach (array_keys(get_object_vars($extensions)) as $namespace) {
            $pointer = str_replace(['~', '/'], ['~0', '~1'], $namespace);
            $violations[] = new GraphViolation(ViolationCategory::Capability, 'missing_extension_schema', $path . '/' . $pointer, 'Extension namespace has no registered schema.');
        }

        return $violations;
    }
}
