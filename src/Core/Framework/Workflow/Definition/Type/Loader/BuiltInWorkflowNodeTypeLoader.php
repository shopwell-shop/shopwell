<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Definition\Type\Loader;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeKind;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeTypeSpecification;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowNodeTypes as DocumentNodeTypes;

/**
 * @internal
 */
#[Package('framework')]
final class BuiltInWorkflowNodeTypeLoader implements WorkflowNodeTypeLoader
{
    public function load(): iterable
    {
        yield new WorkflowNodeTypeSpecification(DocumentNodeTypes::START, 1, WorkflowNodeKind::START, [], ['out']);
        yield new WorkflowNodeTypeSpecification(DocumentNodeTypes::END, 1, WorkflowNodeKind::END, ['in'], []);
        yield new WorkflowNodeTypeSpecification(DocumentNodeTypes::ACTION, 1, WorkflowNodeKind::ACTIVITY, ['in'], ['out']);
        yield new WorkflowNodeTypeSpecification(DocumentNodeTypes::HUMAN_TASK, 1, WorkflowNodeKind::ACTIVITY, ['in'], ['done']);
        yield new WorkflowNodeTypeSpecification(DocumentNodeTypes::APPROVAL, 1, WorkflowNodeKind::ACTIVITY, ['in'], ['approved', 'rejected']);
    }
}
