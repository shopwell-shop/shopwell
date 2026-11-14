<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;
use Shopwell\Core\Framework\Workflow\Definition\WorkflowDocument;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;

/**
 * @internal
 */
#[Package('framework')]
final readonly class StartWorkflowNodeCompiler implements WorkflowNodeCompiler
{
    public function __construct(private WorkflowInputSchema $inputs)
    {
    }

    public function getNodeType(): string
    {
        return 'core:start';
    }

    public function getNodeVersion(): int
    {
        return 1;
    }

    public function compile(WorkflowNode $node, WorkflowDocument $document, Context $context): GraphNode
    {
        $config = (array) $node->config;
        $schema = $this->inputs->normalize($config['formFields'] ?? []);

        return new GraphNode($node->id, WorkflowNodeTypes::START, 0, 0, [
            'name' => $config['label'] ?? $node->id,
            'inputSchema' => $schema,
        ]);
    }
}
