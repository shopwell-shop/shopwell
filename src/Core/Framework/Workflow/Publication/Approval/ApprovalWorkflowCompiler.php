<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;
use Shopwell\Core\Framework\Workflow\Definition\WorkflowDocument;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowCountersignTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
final class ApprovalWorkflowCompiler
{
    public function __construct(
        private readonly WorkflowInputSchema $forms = new WorkflowInputSchema(),
        private readonly ApprovalCondition $conditions = new ApprovalCondition(),
    ) {
    }

    /**
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public function compile(WorkflowDocument $document): array
    {
        try {
            return $this->compileTree($document);
        } catch (WorkflowExecutionException) {
            throw WorkflowException::publicationRejected('unsupported_approval_workflow');
        }
    }

    /**
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    private function compileTree(WorkflowDocument $document): array
    {
        if ($document->policy !== 'core:structured') {
            throw WorkflowException::publicationRejected('unsupported_approval_workflow');
        }
        $nodes = [];
        $starts = [];
        foreach ($document->nodes as $node) {
            if (\strlen($node->id) > 64 || $node->typeVersion !== 1 || !\in_array($node->type, ['core:start', 'core:approval', 'core:condition', 'core:end'], true) || isset($nodes[$node->id])) {
                throw WorkflowException::publicationRejected('unsupported_approval_workflow');
            }
            $nodes[$node->id] = $node;
            if ($node->type === 'core:start') {
                $starts[] = $node;
            }
            if ($node->type === 'core:end' && $this->map($node->config) !== []) {
                throw WorkflowException::publicationRejected('unsupported_approval_workflow');
            }
        }
        if (\count($starts) !== 1 || array_filter($nodes, static fn (WorkflowNode $node): bool => $node->type === 'core:approval') === []) {
            throw WorkflowException::publicationRejected('unsupported_approval_workflow');
        }
        $outputs = [];
        $connectionIds = [];
        $incoming = [];
        foreach ($document->connections as $connection) {
            if (isset($outputs[$connection->source][$connection->sourcePort]) || isset($connectionIds[$connection->id]) || $connection->targetPort !== 'in'
                || !isset($nodes[$connection->source], $nodes[$connection->target]) || $nodes[$connection->source]->type === 'core:end'
                || $nodes[$connection->target]->type === 'core:start'
                || (isset($incoming[$connection->target]) && $nodes[$connection->target]->type !== 'core:end')) {
                throw WorkflowException::publicationRejected('unsupported_approval_workflow');
            }
            $outputs[$connection->source][$connection->sourcePort] = $connection;
            $connectionIds[$connection->id] = true;
            $incoming[$connection->target] = true;
        }
        $start = $starts[0];
        $config = $this->map($start->config);
        if ($config === null || array_diff(array_keys($config), ['inputType', 'formFields', 'label', 'description', 'requestAction']) !== []
            || (\array_key_exists('inputType', $config) && $config['inputType'] !== 'core:approval-request')) {
            throw WorkflowException::publicationRejected('unsupported_approval_workflow');
        }
        $this->assertMetadata($config);
        if (isset($config['requestAction']) && (!\is_string($config['requestAction']) || preg_match('/^[a-z][a-z0-9_-]*:[a-z][a-z0-9_-]*$/D', $config['requestAction']) !== 1)) {
            throw WorkflowException::publicationRejected('unsupported_approval_workflow');
        }
        $schema = $this->forms->normalize(\array_key_exists('formFields', $config) ? $config['formFields'] : [['key' => 'reason', 'label' => '申请事由', 'type' => 'text']]);
        $compiled = [];
        $edges = [];
        $visited = [];
        $endId = null;
        $queue = [$start->id];
        while ($queue !== []) {
            $id = array_shift($queue);
            $current = $nodes[$id];
            if (isset($visited[$id])) {
                if ($current->type === 'core:end') {
                    continue;
                }
                throw WorkflowException::publicationRejected('unsupported_approval_workflow');
            }
            $visited[$id] = true;
            $config = $this->map($current->config);
            if ($current->type === 'core:end') {
                $endId ??= $id;
                continue;
            }
            if ($current->type === 'core:start') {
                $compiled[] = ['id' => $id, 'type' => WorkflowNodeTypes::START, 'properties' => ['inputSchema' => $schema]];
                $ports = ['out' => null];
            } elseif ($current->type === 'core:approval') {
                $compiled[] = ['id' => $id, 'type' => WorkflowNodeTypes::TASK, 'properties' => [
                    'approvalStage' => true,
                    'name' => \is_string($config['label'] ?? null) && trim($config['label']) !== '' ? $config['label'] : '审批',
                    'description' => $config['description'] ?? '',
                    'assigneeType' => WorkflowAssigneeTypes::USER,
                    ...$this->approvalConfiguration($current),
                ]];
                $rejected = $outputs[$id]['rejected'] ?? null;
                if ($rejected === null || $nodes[$rejected->target]->type !== 'core:end') {
                    throw WorkflowException::publicationRejected('unsupported_approval_workflow');
                }
                $visited[$rejected->target] = true;
                $ports = ['approved' => null];
            } else {
                $ports = $this->conditionPorts($config, $schema);
                $compiled[] = ['id' => $id, 'type' => WorkflowNodeTypes::CONDITION, 'properties' => ['name' => $config['label'] ?? '条件分支']];
            }
            $expected = $current->type === 'core:approval' ? ['approved', 'rejected'] : array_keys($ports);
            if (\count($outputs[$id] ?? []) !== \count($expected) || array_diff(array_keys($outputs[$id] ?? []), $expected) !== []) {
                throw WorkflowException::publicationRejected('unsupported_approval_workflow');
            }
            foreach ($ports as $port => $expression) {
                $next = $outputs[$id][$port];
                $edge = ['id' => $next->id, 'sourceNodeId' => $id, 'targetNodeId' => $next->target];
                if ($expression !== null) {
                    $edge['properties'] = ['expr' => $expression];
                    foreach ($config['branches'] ?? [] as $rawBranch) {
                        $branch = $this->map($rawBranch);
                        if (($branch['id'] ?? null) === $port) {
                            $edge['properties']['name'] = $branch['label'];
                        }
                    }
                }
                if ($port === 'fallback') {
                    $edge['properties']['name'] = '默认分支';
                }
                $edges[] = $edge;
                if ($nodes[$next->target]->type === 'core:end') {
                    $endId ??= $next->target;
                }
                $queue[] = $next->target;
            }
        }
        if (\count($visited) !== \count($nodes) || $endId === null) {
            throw WorkflowException::publicationRejected('unsupported_approval_workflow');
        }
        $compiled[] = ['id' => $endId, 'type' => WorkflowNodeTypes::END];
        foreach ($edges as &$compiledEdge) {
            if ($nodes[$compiledEdge['targetNodeId']]->type === 'core:end') {
                $compiledEdge['properties']['workflowTargetNodeId'] = $compiledEdge['targetNodeId'];
                $compiledEdge['targetNodeId'] = $endId;
            }
        }
        unset($compiledEdge);

        // Runtime rejection terminates the instance; only approval and condition edges advance it.
        return ['nodes' => $compiled, 'edges' => $edges];
    }

    /**
     * @param array<array-key, mixed>|null $config
     * @param list<array<string, mixed>> $schema
     *
     * @return array<string, string|null>
     */
    private function conditionPorts(?array $config, array $schema): array
    {
        if ($config === null || array_diff(array_keys($config), ['branches', 'label', 'description']) !== []
            || !\is_array($config['branches'] ?? null) || !array_is_list($config['branches']) || $config['branches'] === [] || \count($config['branches']) > 50) {
            throw WorkflowException::publicationRejected('unsupported_approval_workflow');
        }
        $this->assertMetadata($config);
        $ports = [];
        foreach ($config['branches'] as $raw) {
            $branch = $this->map($raw);
            if ($branch === null || array_diff(array_keys($branch), ['id', 'label', 'field', 'fieldType', 'operator', 'value']) !== []
                || !\is_string($branch['id'] ?? null) || !preg_match('/^branch-[a-zA-Z0-9-]+$/D', $branch['id']) || isset($ports[$branch['id']])
                || !\is_string($branch['label'] ?? null) || trim($branch['label']) === '' || mb_strlen($branch['label']) > 255) {
                throw WorkflowException::publicationRejected('unsupported_approval_workflow');
            }
            $ports[$branch['id']] = $this->conditions->encode($branch, $schema);
        }
        $ports['fallback'] = null;

        return $ports;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function assertMetadata(array $config): void
    {
        if ((\array_key_exists('label', $config) && (!\is_string($config['label']) || mb_strlen($config['label']) > 255))
            || (\array_key_exists('description', $config) && !\is_string($config['description']))) {
            throw WorkflowException::publicationRejected('unsupported_approval_workflow');
        }
    }

    /**
     * @return array{assigneeValue: list<string>, countersignType: string}
     */
    private function approvalConfiguration(WorkflowNode $node): array
    {
        $config = $this->map($node->config);
        $assignee = $this->map($config['assignee'] ?? null);
        $approval = $this->map($config['approval'] ?? null);
        $userConfig = $this->map($assignee['config'] ?? null);
        if ($config === null || array_diff(array_keys($config), ['assignee', 'approval', 'label', 'description']) !== []
            || !isset($config['assignee'], $config['approval']) || (\array_key_exists('label', $config) && (!\is_string($config['label']) || mb_strlen($config['label']) > 255))
            || (\array_key_exists('description', $config) && !\is_string($config['description'])) || !$this->keys($assignee, ['source', 'config']) || !$this->keys($approval, ['mode', 'config'])
            || $this->map($approval['config'] ?? null) !== []) {
            throw WorkflowException::publicationRejected('unsupported_approval_workflow');
        }

        if (($assignee['source'] ?? null) === 'core:user' && ($approval['mode'] ?? null) === 'core:single' && $this->keys($userConfig, ['userId'])) {
            $ids = [$userConfig['userId'] ?? null];
            $mode = WorkflowCountersignTypes::SINGLE;
        } elseif (($assignee['source'] ?? null) === 'core:users' && \in_array($approval['mode'] ?? null, ['core:all', 'core:any'], true) && $this->keys($userConfig, ['userIds'])) {
            $ids = $userConfig['userIds'] ?? null;
            $mode = $approval['mode'] === 'core:all' ? WorkflowCountersignTypes::COUNTERSIGN : WorkflowCountersignTypes::FIRST_DECISION;
        } else {
            throw WorkflowException::publicationRejected('unsupported_approval_workflow');
        }
        if (!\is_array($ids) || !array_is_list($ids) || $ids === []) {
            throw WorkflowException::publicationRejected('unsupported_approval_workflow');
        }
        $normalized = [];
        foreach ($ids as $id) {
            if (!\is_string($id) || !Uuid::isValid(strtolower($id)) || \in_array(strtolower($id), $normalized, true)) {
                throw WorkflowException::publicationRejected('unsupported_approval_workflow');
            }
            $normalized[] = strtolower($id);
        }

        return ['assigneeValue' => $normalized, 'countersignType' => $mode];
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

    /**
     * @param array<array-key, mixed>|null $value
     * @param list<string> $keys
     */
    private function keys(?array $value, array $keys): bool
    {
        return $value !== null && \count($value) === \count($keys) && array_diff(array_keys($value), $keys) === [];
    }
}
