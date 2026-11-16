<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\WorkflowDocument;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowGraphDiagnostics;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowViolation;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\WorkflowGraphValidator;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
final readonly class WorkflowCompiler
{
    public function __construct(private WorkflowNodeCompilerRegistry $compilers, private WorkflowGraphDiagnostics $diagnostics, private WorkflowGraphValidator $validator)
    {
    }

    public function compile(WorkflowDocument $document, Context $context): GraphData
    {
        foreach ($document->nodes as $index => $node) {
            if (mb_strlen($node->id) > 64) {
                throw WorkflowException::publicationRejected('runtime_identifier_too_long', [
                    new WorkflowViolation('runtime_identifier_too_long', '/nodes/' . $index . '/id', $node->id),
                ]);
            }
        }

        foreach ($document->connections as $index => $connection) {
            if (mb_strlen($connection->id) > 64) {
                throw WorkflowException::publicationRejected('runtime_identifier_too_long', [
                    new WorkflowViolation('runtime_identifier_too_long', '/connections/' . $index . '/id', $connection->source),
                ]);
            }
        }

        if ($this->diagnostics->analyze($document) !== []) {
            throw WorkflowException::publicationRejected('invalid_workflow_document');
        }

        $nodes = [];
        $edges = [];

        foreach ($document->nodes as $node) {
            $compiled = $this->compilers->get($node->type, $node->typeVersion)->compile($node, $document, $context);

            if ($compiled->id !== $node->id) {
                throw WorkflowException::publicationRejected('node_compiler_changed_identity');
            }

            $compiledNode = $compiled->toArray();
            $compiledNode['properties']['workflowNodeType'] = $node->type;
            $compiledNode['properties']['workflowNodeTypeVersion'] = $node->typeVersion;
            $nodes[] = $compiledNode;
        }

        foreach ($document->connections as $connection) {
            $edges[] = [
                'id' => $connection->id,
                'sourceNodeId' => $connection->source,
                'targetNodeId' => $connection->target,
                'properties' => ['sourcePort' => $connection->sourcePort, 'targetPort' => $connection->targetPort],
            ];
        }

        $graph = GraphData::fromArray(['nodes' => $nodes, 'edges' => $edges]);
        $violations = $this->validator->validate($graph, new ValidationContext());

        if ($this->validator->hasErrors($violations)) {
            throw WorkflowException::publicationRejected('workflow_execution_unavailable');
        }

        return $graph;
    }
}
