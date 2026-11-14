<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;
use Shopwell\Core\Framework\Workflow\Definition\WorkflowDocument;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;

/**
 * Register executable node configuration with shopwell.workflow.node_compiler.
 */
#[Package('framework')]
interface WorkflowNodeCompiler
{
    public function getNodeType(): string;

    public function getNodeVersion(): int;

    public function compile(WorkflowNode $node, WorkflowDocument $document, Context $context): GraphNode;
}
