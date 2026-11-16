<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval;

use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Connection\WorkflowConnection;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;
use Shopwell\Core\Framework\Workflow\Definition\WorkflowDocument;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowViolation;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Extension\CompileApprovalNodeExtension;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompilationContext;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompilerRegistry;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeRole;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\CompiledApprovalNode;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 *
 * @phpstan-type Stage array{node: CompiledApprovalNode, role: ApprovalNodeRole, compiler: array{type: string, version: int}}
 */
#[Package('framework')]
final class ApprovalWorkflowCompiler
{
    public function __construct(
        private readonly ApprovalNodeCompilerRegistry $compilers,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    /**
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public function compile(WorkflowDocument $document): array
    {
        if ($document->policy !== 'core:structured') {
            throw $this->rejected('unsupported_policy', '/policy');
        }

        $nodes = $this->indexNodes($document);
        $stages = $this->compileNodes($document);
        $startId = $this->findStart($stages);
        $outputs = $this->validateConnections($document, $nodes, $stages);
        [$orderedIds, $endId] = $this->orderReachableStages($nodes, $stages, $outputs, $startId);

        return $this->generateExecutionGraph($stages, $outputs, $orderedIds, $endId);
    }

    /**
     * @return array<string, WorkflowNode>
     */
    private function indexNodes(WorkflowDocument $document): array
    {
        $nodes = [];

        foreach ($document->nodes as $index => $node) {
            $path = '/nodes/' . $index;

            if (mb_strlen($node->id) > 64) {
                throw $this->rejected('runtime_identifier_too_long', $path . '/id', $node->id);
            }

            if ($this->compilers->find($node->type, $node->typeVersion) === null) {
                $code = $this->compilers->hasType($node->type) ? 'unsupported_node_version' : 'unsupported_node_type';
                $field = $this->compilers->hasType($node->type) ? '/typeVersion' : '/type';

                throw $this->rejected($code, $path . $field, $node->id);
            }

            if (isset($nodes[$node->id])) {
                throw $this->rejected('duplicate_node_id', $path . '/id', $node->id);
            }

            $nodes[$node->id] = $node;
        }

        return $nodes;
    }

    /**
     * @param array<string, Stage> $stages
     */
    private function findStart(array $stages): string
    {
        $starts = array_keys(array_filter($stages, static fn (array $stage): bool => $stage['role'] === ApprovalNodeRole::START));

        if (\count($starts) !== 1) {
            throw $this->rejected('single_start_required', '/nodes');
        }

        if (array_filter($stages, static fn (array $stage): bool => $stage['role'] === ApprovalNodeRole::APPROVAL) === []) {
            throw $this->rejected('approval_required', '/nodes');
        }

        return $starts[0];
    }

    /**
     * @return array<string, Stage>
     */
    private function compileNodes(WorkflowDocument $document): array
    {
        $stages = [];
        $schema = [];
        $pending = [];

        // Compile the start first so every other compiler receives the normalized input schema.
        foreach ($document->nodes as $index => $node) {
            $compiler = $this->compilers->find($node->type, $node->typeVersion);

            if ($compiler === null) {
                throw $this->rejected('unsupported_node_type', '/nodes/' . $index . '/type', $node->id);
            }

            if ($compiler->getRole() !== ApprovalNodeRole::START) {
                $pending[] = [$index, $node, $compiler];
                continue;
            }

            if ($stages !== []) {
                throw $this->rejected('single_start_required', '/nodes');
            }

            $stage = $this->compileNode($compiler, $node, $index, []);
            $schema = $this->inputSchema($stage['node'], $node->id);
            $stages[$node->id] = $stage;
        }

        if ($stages === []) {
            throw $this->rejected('single_start_required', '/nodes');
        }

        foreach ($pending as [$index, $node, $compiler]) {
            $stages[$node->id] = $this->compileNode($compiler, $node, $index, $schema);
        }

        return $stages;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inputSchema(CompiledApprovalNode $node, string $nodeId): array
    {
        $schema = $node->properties['inputSchema'] ?? null;

        if (!\is_array($schema) || !array_is_list($schema)) {
            throw $this->rejected('invalid_input_schema', '/nodes', $nodeId);
        }

        foreach ($schema as $field) {
            if (!\is_array($field) || array_filter(array_keys($field), \is_int(...)) !== []) {
                throw $this->rejected('invalid_input_schema', '/nodes', $nodeId);
            }
        }

        return $schema;
    }

    /**
     * @param list<array<string, mixed>> $schema
     *
     * @return Stage
     */
    private function compileNode(ApprovalNodeCompiler $compiler, WorkflowNode $node, int $index, array $schema): array
    {
        $context = new ApprovalNodeCompilationContext($node->id, $node->config, '/nodes/' . $index . '/config', $schema);
        $extension = new CompileApprovalNodeExtension($compiler, $context);
        $expectedRole = $compiler->getRole();
        $usedCompiler = null;
        $compilerResult = null;
        $compiled = $this->extensions->publish(
            CompileApprovalNodeExtension::NAME,
            $extension,
            function (ApprovalNodeCompiler $compiler, ApprovalNodeCompilationContext $context) use (&$usedCompiler, &$compilerResult, $expectedRole): CompiledApprovalNode {
                $this->validateCompilerSelection($compiler, $context, $expectedRole);
                $usedCompiler = $compiler;

                $compilerResult = $compiler->compile($context);

                return $compilerResult;
            },
        );
        $selected = $extension->compiler;

        $this->validateCompilerSelection($selected, $context, $expectedRole);

        if ($usedCompiler !== $selected || $compiled !== $compilerResult) {
            throw $context->reject('invalid_node_compiler_override');
        }

        $role = $selected->getRole();

        $this->validateCompilation($role, $compiled, $context);

        return [
            'node' => $compiled,
            'role' => $role,
            'compiler' => ['type' => $selected->getNodeType(), 'version' => $selected->getNodeVersion()],
        ];
    }

    private function validateCompilerSelection(ApprovalNodeCompiler $compiler, ApprovalNodeCompilationContext $context, ApprovalNodeRole $expectedRole): void
    {
        if ($this->compilers->find($compiler->getNodeType(), $compiler->getNodeVersion()) !== $compiler
            || $compiler->getRole() !== $expectedRole) {
            throw $context->reject('invalid_node_compiler_override');
        }
    }

    private function validateCompilation(ApprovalNodeRole $role, CompiledApprovalNode $compiled, ApprovalNodeCompilationContext $context): void
    {
        if ($role === ApprovalNodeRole::START) {
            if ($compiled->type !== WorkflowNodeTypes::START || $compiled->inputPorts !== [] || $compiled->terminalPorts !== []) {
                throw $context->reject('invalid_node_compilation');
            }

            return;
        }

        if ($role === ApprovalNodeRole::END) {
            if ($compiled->type !== WorkflowNodeTypes::END || $compiled->outputPorts !== [] || $compiled->terminalPorts !== []) {
                throw $context->reject('invalid_node_compilation');
            }

            return;
        }

        if (\in_array($compiled->type, [WorkflowNodeTypes::START, WorkflowNodeTypes::END], true)) {
            throw $context->reject('invalid_node_compilation');
        }
    }

    /**
     * @param array<string, WorkflowNode> $nodes
     * @param array<string, Stage> $stages
     *
     * @return array<string, array<string, WorkflowConnection>>
     */
    private function validateConnections(WorkflowDocument $document, array $nodes, array $stages): array
    {
        $outputs = [];
        $connectionIds = [];
        $incoming = [];

        foreach ($document->connections as $index => $connection) {
            $path = '/connections/' . $index;

            if (mb_strlen($connection->id) > 64) {
                throw $this->rejected('runtime_identifier_too_long', $path . '/id', $connection->source);
            }

            if (isset($connectionIds[$connection->id])) {
                throw $this->rejected('duplicate_connection_id', $path . '/id', $connection->source);
            }

            if (!isset($nodes[$connection->source])) {
                throw $this->rejected('unknown_source', $path . '/source');
            }

            if (!isset($nodes[$connection->target])) {
                throw $this->rejected('unknown_target', $path . '/target', $connection->source);
            }

            if (!\in_array($connection->targetPort, $stages[$connection->target]['node']->inputPorts, true)) {
                throw $this->rejected('unknown_input_port', $path . '/targetPort', $connection->target);
            }

            if (isset($outputs[$connection->source][$connection->sourcePort])
                || (!\array_key_exists($connection->sourcePort, $stages[$connection->source]['node']->outputPorts)
                    && !\in_array($connection->sourcePort, $stages[$connection->source]['node']->terminalPorts, true))) {
                throw $this->rejected('invalid_output_port', $path . '/sourcePort', $connection->source);
            }

            if ($stages[$connection->target]['role'] === ApprovalNodeRole::START
                || (isset($incoming[$connection->target]) && $stages[$connection->target]['role'] !== ApprovalNodeRole::END)) {
                throw $this->rejected('invalid_incoming_connection', $path . '/target', $connection->target);
            }

            if (\in_array($connection->sourcePort, $stages[$connection->source]['node']->terminalPorts, true) && $stages[$connection->target]['role'] !== ApprovalNodeRole::END) {
                throw $this->rejected('rejection_must_terminate', $path . '/target', $connection->source);
            }

            $outputs[$connection->source][$connection->sourcePort] = $connection;
            $connectionIds[$connection->id] = true;
            $incoming[$connection->target] = true;
        }

        foreach ($document->nodes as $index => $node) {
            $compiled = $stages[$node->id]['node'];
            $expectedOutputs = \count($compiled->outputPorts) + \count($compiled->terminalPorts);

            if (\count($outputs[$node->id] ?? []) !== $expectedOutputs) {
                throw $this->rejected('missing_output_connection', '/nodes/' . $index, $node->id);
            }
        }

        return $outputs;
    }

    /**
     * @param array<string, WorkflowNode> $nodes
     * @param array<string, Stage> $stages
     * @param array<string, array<string, WorkflowConnection>> $outputs
     *
     * @return array{list<string>, string}
     */
    private function orderReachableStages(array $nodes, array $stages, array $outputs, string $startId): array
    {
        $visited = [];
        $orderedIds = [];
        $endId = null;
        $queue = [$startId];

        while ($queue !== []) {
            $id = array_shift($queue);
            $stage = $stages[$id];

            if (isset($visited[$id])) {
                if ($stage['role'] === ApprovalNodeRole::END) {
                    continue;
                }

                throw $this->rejected('cycle_or_shared_activity', '/nodes', $id);
            }

            $visited[$id] = true;

            if ($stage['role'] === ApprovalNodeRole::END) {
                $endId ??= $id;
                continue;
            }

            $orderedIds[] = $id;

            foreach ($stage['node']->terminalPorts as $port) {
                $visited[$outputs[$id][$port]->target] = true;
            }

            foreach ($stage['node']->outputPorts as $port => $properties) {
                $target = $outputs[$id][$port]->target;

                if ($stages[$target]['role'] === ApprovalNodeRole::END) {
                    $endId ??= $target;
                }

                $queue[] = $target;
            }
        }

        foreach ($nodes as $id => $node) {
            if (!isset($visited[$id])) {
                throw $this->rejected('unreachable_node', '/nodes', $id);
            }
        }

        if ($endId === null) {
            throw $this->rejected('end_required', '/nodes');
        }

        return [$orderedIds, $endId];
    }

    /**
     * @param array<string, Stage> $stages
     * @param array<string, array<string, WorkflowConnection>> $outputs
     * @param list<string> $orderedIds
     *
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    private function generateExecutionGraph(array $stages, array $outputs, array $orderedIds, string $endId): array
    {
        $compiled = [];
        $edges = [];

        foreach ($orderedIds as $id) {
            $stage = $stages[$id];
            $compiled[] = [
                'id' => $id,
                'type' => $stage['node']->type,
                'properties' => [...$stage['node']->properties, 'approvalNodeCompiler' => $stage['compiler']],
            ];

            // Terminal design ports are completed by their executor rather than routed at runtime.
            foreach ($stage['node']->outputPorts as $port => $properties) {
                $connection = $outputs[$id][$port];
                $targetId = $connection->target;

                if ($stages[$targetId]['role'] === ApprovalNodeRole::END) {
                    $properties['workflowTargetNodeId'] = $targetId;
                    $targetId = $endId;
                }

                $properties['sourcePort'] = $port;

                $edge = [
                    'id' => $connection->id,
                    'sourceNodeId' => $id,
                    'targetNodeId' => $targetId,
                ];

                $edge['properties'] = $properties;

                $edges[] = $edge;
            }
        }

        $end = $stages[$endId];

        foreach ($stages as $id => $stage) {
            if ($stage['role'] === ApprovalNodeRole::END
                && ($stage['node']->properties !== $end['node']->properties || $stage['compiler'] !== $end['compiler'])) {
                throw $this->rejected('incompatible_end_nodes', '/nodes', $id);
            }
        }

        $compiled[] = [
            'id' => $endId,
            'type' => $end['node']->type,
            'properties' => [...$end['node']->properties, 'approvalNodeCompiler' => $end['compiler']],
        ];

        return ['nodes' => $compiled, 'edges' => $edges];
    }

    private function rejected(string $code, string $path, ?string $nodeId = null): WorkflowException
    {
        return WorkflowException::publicationRejected('unsupported_approval_workflow', [new WorkflowViolation($code, $path, $nodeId)]);
    }
}
