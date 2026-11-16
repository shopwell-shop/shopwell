<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Connection\WorkflowConnection;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowNodeTypes as DocumentNodeTypes;
use Shopwell\Core\Framework\Workflow\Definition\WorkflowDocument;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowViolation;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalConfigurationCompiler;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 *
 * @phpstan-type Stage array{type: string, properties: array<string, mixed>, ports: array<string, array<string, mixed>>, expectedPorts: list<string>}
 */
#[Package('framework')]
final class ApprovalWorkflowCompiler
{
    public function __construct(
        private readonly ApprovalConfigurationCompiler $approvals,
        private readonly WorkflowInputSchema $forms = new WorkflowInputSchema(),
        private readonly ApprovalCondition $conditions = new ApprovalCondition(),
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
        $start = $this->findStart($nodes);
        $stages = $this->parseConfigurations($document, $start);
        $outputs = $this->validateConnections($document, $nodes, $stages);
        [$orderedIds, $endId] = $this->orderReachableStages($nodes, $stages, $outputs, $start->id);

        return $this->generateExecutionGraph($nodes, $stages, $outputs, $orderedIds, $endId);
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

            if ($node->typeVersion !== 1) {
                throw $this->rejected('unsupported_node_version', $path . '/typeVersion', $node->id);
            }

            if (!\in_array($node->type, [DocumentNodeTypes::START, DocumentNodeTypes::APPROVAL, DocumentNodeTypes::CONDITION, DocumentNodeTypes::END], true)) {
                throw $this->rejected('unsupported_node_type', $path . '/type', $node->id);
            }

            if (isset($nodes[$node->id])) {
                throw $this->rejected('duplicate_node_id', $path . '/id', $node->id);
            }

            $nodes[$node->id] = $node;
        }

        return $nodes;
    }

    /**
     * @param array<string, WorkflowNode> $nodes
     */
    private function findStart(array $nodes): WorkflowNode
    {
        $starts = array_values(array_filter($nodes, static fn (WorkflowNode $node): bool => $node->type === DocumentNodeTypes::START));

        if (\count($starts) !== 1) {
            throw $this->rejected('single_start_required', '/nodes');
        }

        if (array_filter($nodes, static fn (WorkflowNode $node): bool => $node->type === DocumentNodeTypes::APPROVAL) === []) {
            throw $this->rejected('approval_required', '/nodes');
        }

        return $starts[0];
    }

    /**
     * @return array<string, Stage>
     */
    private function parseConfigurations(WorkflowDocument $document, WorkflowNode $start): array
    {
        $startIndex = array_search($start, $document->nodes, true);
        $startPath = '/nodes/' . $startIndex . '/config';
        $config = $this->configuration($start->config, ['inputType', 'formFields', 'label', 'description', 'requestAction'], $startPath, $start->id);

        if (\array_key_exists('inputType', $config) && $config['inputType'] !== 'core:approval-request') {
            throw $this->rejected('unsupported_input_type', $startPath . '/inputType', $start->id);
        }

        if (isset($config['requestAction'])
            && (!\is_string($config['requestAction']) || preg_match('/^[a-z][a-z0-9_-]*:[a-z][a-z0-9_-]*$/D', $config['requestAction']) !== 1)) {
            throw $this->rejected('invalid_request_action', $startPath . '/requestAction', $start->id);
        }

        try {
            $schema = $this->forms->normalize(
                \array_key_exists('formFields', $config)
                    ? $config['formFields']
                    : [['key' => 'reason', 'label' => '申请事由', 'type' => 'text']]
            );
        } catch (WorkflowExecutionException $exception) {
            // Input diagnostics use /fields; locate that field within the designer's start configuration.
            $fieldPath = $exception->getParameters()['path'];
            throw $this->rejected('invalid_input_schema', $startPath . '/formFields' . substr($fieldPath, \strlen('/fields')), $start->id);
        }

        $stages = [];

        foreach ($document->nodes as $index => $node) {
            $path = '/nodes/' . $index . '/config';

            if ($node->type === DocumentNodeTypes::START) {
                $stages[$node->id] = [
                    'type' => WorkflowNodeTypes::START,
                    'properties' => ['inputSchema' => $schema],
                    'ports' => ['out' => []],
                    'expectedPorts' => ['out'],
                ];
            } elseif ($node->type === DocumentNodeTypes::APPROVAL) {
                $config = $this->configuration($node->config, ['assignee', 'approval', 'label', 'description'], $path, $node->id);
                $properties = $this->approvalConfiguration($config, $path, $node->id);
                $stages[$node->id] = [
                    'type' => WorkflowNodeTypes::TASK,
                    'properties' => [
                        'approvalStage' => true,
                        'name' => \is_string($config['label'] ?? null) && trim($config['label']) !== '' ? $config['label'] : '审批',
                        'description' => $config['description'] ?? '',
                        ...$properties,
                    ],
                    'ports' => ['approved' => []],
                    'expectedPorts' => ['approved', 'rejected'],
                ];
            } elseif ($node->type === DocumentNodeTypes::CONDITION) {
                $config = $this->configuration($node->config, ['branches', 'label', 'description'], $path, $node->id);
                $ports = $this->conditionPorts($config, $schema, $path, $node->id);
                $stages[$node->id] = [
                    'type' => WorkflowNodeTypes::CONDITION,
                    'properties' => ['name' => $config['label'] ?? '条件分支'],
                    'ports' => $ports,
                    'expectedPorts' => array_keys($ports),
                ];
            } else {
                if ($this->map($node->config) !== []) {
                    throw $this->rejected('unsupported_end_configuration', $path, $node->id);
                }

                $stages[$node->id] = [
                    'type' => WorkflowNodeTypes::END,
                    'properties' => [],
                    'ports' => [],
                    'expectedPorts' => [],
                ];
            }
        }

        return $stages;
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

            if ($connection->targetPort !== 'in') {
                throw $this->rejected('unknown_input_port', $path . '/targetPort', $connection->target);
            }

            if (isset($outputs[$connection->source][$connection->sourcePort])
                || !\in_array($connection->sourcePort, $stages[$connection->source]['expectedPorts'], true)) {
                throw $this->rejected('invalid_output_port', $path . '/sourcePort', $connection->source);
            }

            if ($nodes[$connection->target]->type === DocumentNodeTypes::START
                || (isset($incoming[$connection->target]) && $nodes[$connection->target]->type !== DocumentNodeTypes::END)) {
                throw $this->rejected('invalid_incoming_connection', $path . '/target', $connection->target);
            }

            if ($connection->sourcePort === 'rejected' && $nodes[$connection->target]->type !== DocumentNodeTypes::END) {
                throw $this->rejected('rejection_must_terminate', $path . '/target', $connection->source);
            }

            $outputs[$connection->source][$connection->sourcePort] = $connection;
            $connectionIds[$connection->id] = true;
            $incoming[$connection->target] = true;
        }

        foreach ($document->nodes as $index => $node) {
            if (\count($outputs[$node->id] ?? []) !== \count($stages[$node->id]['expectedPorts'])) {
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
            $current = $nodes[$id];

            if (isset($visited[$id])) {
                if ($current->type === DocumentNodeTypes::END) {
                    continue;
                }

                throw $this->rejected('cycle_or_shared_activity', '/nodes', $id);
            }

            $visited[$id] = true;

            if ($current->type === DocumentNodeTypes::END) {
                $endId ??= $id;
                continue;
            }

            $orderedIds[] = $id;

            if ($current->type === DocumentNodeTypes::APPROVAL) {
                $visited[$outputs[$id]['rejected']->target] = true;
            }

            foreach ($stages[$id]['ports'] as $port => $properties) {
                $target = $outputs[$id][$port]->target;

                if ($nodes[$target]->type === DocumentNodeTypes::END) {
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
     * @param array<string, WorkflowNode> $nodes
     * @param array<string, Stage> $stages
     * @param array<string, array<string, WorkflowConnection>> $outputs
     * @param list<string> $orderedIds
     *
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    private function generateExecutionGraph(array $nodes, array $stages, array $outputs, array $orderedIds, string $endId): array
    {
        $compiled = [];
        $edges = [];

        foreach ($orderedIds as $id) {
            $stage = $stages[$id];
            $compiled[] = [
                'id' => $id,
                'type' => $stage['type'],
                'properties' => $stage['properties'],
            ];

            // Runtime rejection terminates the instance; only approval and condition edges advance it.
            foreach ($stage['ports'] as $port => $properties) {
                $connection = $outputs[$id][$port];
                $targetId = $connection->target;

                if ($nodes[$targetId]->type === DocumentNodeTypes::END) {
                    $properties['workflowTargetNodeId'] = $targetId;
                    $targetId = $endId;
                }

                $edge = [
                    'id' => $connection->id,
                    'sourceNodeId' => $id,
                    'targetNodeId' => $targetId,
                ];

                if ($properties !== []) {
                    $edge['properties'] = $properties;
                }

                $edges[] = $edge;
            }
        }

        $compiled[] = ['id' => $endId, 'type' => WorkflowNodeTypes::END];

        return ['nodes' => $compiled, 'edges' => $edges];
    }

    /**
     * @param array<array-key, mixed> $config
     * @param list<array<string, mixed>> $schema
     *
     * @return array<string, array<string, mixed>>
     */
    private function conditionPorts(array $config, array $schema, string $path, string $nodeId): array
    {
        $branches = $config['branches'] ?? null;

        if (!\is_array($branches) || !array_is_list($branches) || $branches === [] || \count($branches) > 50) {
            throw $this->rejected('invalid_branches', $path . '/branches', $nodeId);
        }

        $ports = [];

        foreach ($branches as $index => $raw) {
            $branchPath = $path . '/branches/' . $index;
            $branch = $this->map($raw);

            if ($branch === null || array_diff(array_keys($branch), ['id', 'label', 'field', 'fieldType', 'operator', 'value']) !== []) {
                throw $this->rejected('invalid_branch_configuration', $branchPath, $nodeId);
            }

            if (!\is_string($branch['id'] ?? null) || preg_match('/^branch-[a-zA-Z0-9-]+$/D', $branch['id']) !== 1 || isset($ports[$branch['id']])) {
                throw $this->rejected('invalid_branch_id', $branchPath . '/id', $nodeId);
            }

            if (!\is_string($branch['label'] ?? null) || trim($branch['label']) === '' || mb_strlen($branch['label']) > 255) {
                throw $this->rejected('invalid_branch_label', $branchPath . '/label', $nodeId);
            }

            try {
                $expression = $this->conditions->encode($branch, $schema);
            } catch (WorkflowExecutionException) {
                throw $this->rejected('invalid_branch_condition', $branchPath, $nodeId);
            }

            $ports[$branch['id']] = ['expr' => $expression, 'name' => $branch['label']];
        }

        $ports['fallback'] = ['name' => '默认分支'];

        return $ports;
    }

    /**
     * @param list<string> $allowedKeys
     *
     * @return array<array-key, mixed>
     */
    private function configuration(mixed $raw, array $allowedKeys, string $path, string $nodeId): array
    {
        $config = $this->map($raw);

        if ($config === null) {
            throw $this->rejected('invalid_configuration', $path, $nodeId);
        }

        $unknown = array_diff(array_keys($config), $allowedKeys);

        if ($unknown !== []) {
            throw $this->rejected('unknown_configuration', $path . '/' . reset($unknown), $nodeId);
        }

        if (\array_key_exists('label', $config) && (!\is_string($config['label']) || mb_strlen($config['label']) > 255)) {
            throw $this->rejected('invalid_label', $path . '/label', $nodeId);
        }

        if (\array_key_exists('description', $config) && !\is_string($config['description'])) {
            throw $this->rejected('invalid_description', $path . '/description', $nodeId);
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function approvalConfiguration(array $config, string $path, string $nodeId): array
    {
        $compiled = $this->approvals->compile($config, $path, $nodeId);

        return [
            'assigneeType' => $compiled->assignment->type,
            'assigneeValue' => $compiled->assignment->values,
            'assigneeConfig' => $compiled->assignment->configuration,
            'assigneeUserIds' => $compiled->assignment->knownUserIds,
            'countersignType' => $compiled->mode->getType(),
            'approvalModeVersion' => $compiled->mode->getVersion(),
            'approvalModeConfig' => $compiled->configuration,
        ];
    }

    private function rejected(string $code, string $path, ?string $nodeId = null): WorkflowException
    {
        return WorkflowException::publicationRejected('unsupported_approval_workflow', [new WorkflowViolation($code, $path, $nodeId)]);
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function map(mixed $value): ?array
    {
        if ($value instanceof \stdClass) {
            return get_object_vars($value);
        }

        return \is_array($value) && ($value === [] || !array_is_list($value)) ? $value : null;
    }
}
