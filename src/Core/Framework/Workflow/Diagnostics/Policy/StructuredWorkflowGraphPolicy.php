<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Diagnostics\Policy;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeKind;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeTypeSpecification;
use Shopwell\Core\Framework\Workflow\Definition\WorkflowDocument;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowViolation;

/**
 * @internal Checks control-flow structure, not node configuration or executable capabilities.
 */
#[Package('framework')]
final readonly class StructuredWorkflowGraphPolicy implements WorkflowGraphPolicy
{
    public function getName(): string
    {
        return 'core:structured';
    }

    /**
     * @param array<string, WorkflowNodeTypeSpecification> $types
     *
     * @return list<WorkflowViolation>
     */
    public function analyze(WorkflowDocument $document, array $types): array
    {
        $diagnostics = [];
        $nodes = [];
        foreach ($document->nodes as $node) {
            $nodes[$node->id] = $node;
        }

        $starts = [];
        $ends = [];
        foreach ($document->nodes as $node) {
            $type = $types[$node->id];
            if ($type->kind === WorkflowNodeKind::START) {
                $starts[] = $node->id;
            }
            if ($type->kind === WorkflowNodeKind::END) {
                $ends[] = $node->id;
            }
        }

        if (\count($starts) !== 1) {
            $diagnostics[] = new WorkflowViolation('start_count', '/nodes');
        }
        if ($ends === []) {
            $diagnostics[] = new WorkflowViolation('end_missing', '/nodes');
        }

        /** @var array<string, list<string>> $forward */
        $forward = [];
        /** @var array<string, list<string>> $backward */
        $backward = [];
        $outputs = [];
        $inputs = [];
        foreach ($document->connections as $index => $connection) {
            $path = '/connections/' . $index;
            $source = $nodes[$connection->source];
            $target = $nodes[$connection->target];

            if (isset($outputs[$source->id][$connection->sourcePort])) {
                $diagnostics[] = new WorkflowViolation('multiple_output_connections', $path . '/sourcePort', $source->id);
            }
            if (isset($inputs[$target->id][$connection->targetPort])) {
                $diagnostics[] = new WorkflowViolation('multiple_input_connections', $path . '/targetPort', $target->id);
            }
            $outputs[$source->id][$connection->sourcePort] = true;
            $inputs[$target->id][$connection->targetPort] = true;
            $forward[$source->id][] = $target->id;
            $backward[$target->id][] = $source->id;
        }

        if ($this->hasCycle($document->nodes, $forward, $backward)) {
            $diagnostics[] = new WorkflowViolation('cycle', '/connections');
        }

        $reachable = $this->reachable($starts, $forward);
        $terminating = $this->reachable($ends, $backward);
        foreach ($document->nodes as $index => $node) {
            $path = '/nodes/' . $index;
            if (\count($starts) === 1 && !isset($reachable[$node->id])) {
                $diagnostics[] = new WorkflowViolation('unreachable_node', $path, $node->id);
            }
            if ($ends !== [] && !isset($terminating[$node->id])) {
                $diagnostics[] = new WorkflowViolation('no_path_to_end', $path, $node->id);
            }
            $nodeType = $types[$node->id] ?? null;
            if ($nodeType === null) {
                continue;
            }
            foreach ($nodeType->outputPorts as $port) {
                if (!isset($outputs[$node->id][$port])) {
                    $diagnostics[] = new WorkflowViolation('unconnected_output', $path, $node->id);
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @param list<string> $seeds
     * @param array<string, list<string>> $adjacency
     *
     * @return array<string, true>
     */
    private function reachable(array $seeds, array $adjacency): array
    {
        $visited = [];
        while (($id = array_pop($seeds)) !== null) {
            if (isset($visited[$id])) {
                continue;
            }
            $visited[$id] = true;
            foreach ($adjacency[$id] ?? [] as $next) {
                $seeds[] = $next;
            }
        }

        return $visited;
    }

    /**
     * @param list<WorkflowNode> $nodes
     * @param array<string, list<string>> $forward
     * @param array<string, list<string>> $backward
     */
    private function hasCycle(array $nodes, array $forward, array $backward): bool
    {
        $incoming = [];
        $ready = [];
        foreach ($nodes as $node) {
            $incoming[$node->id] = \count($backward[$node->id] ?? []);
            if ($incoming[$node->id] === 0) {
                $ready[] = $node->id;
            }
        }

        $visited = 0;
        while (($id = array_pop($ready)) !== null) {
            ++$visited;
            foreach ($forward[$id] ?? [] as $target) {
                --$incoming[$target];
                if ($incoming[$target] === 0) {
                    $ready[] = $target;
                }
            }
        }

        return $visited !== \count($nodes);
    }
}
