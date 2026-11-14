<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;
use Shopwell\Core\Framework\Workflow\Definition\WorkflowDocument;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
final class EndWorkflowNodeCompiler implements WorkflowNodeCompiler
{
    public function getNodeType(): string
    {
        return 'core:end';
    }

    public function getNodeVersion(): int
    {
        return 1;
    }

    public function compile(WorkflowNode $node, WorkflowDocument $document, Context $context): GraphNode
    {
        $config = (array) $node->config;
        $result = $config['result'] ?? 'completed';

        if (!\is_string($result) || $result === '' || \strlen($result) > 64) {
            throw WorkflowException::publicationRejected('invalid_completion_result');
        }

        return new GraphNode($node->id, WorkflowNodeTypes::END, 0, 0, ['name' => $config['label'] ?? $node->id, 'result' => $result]);
    }
}
