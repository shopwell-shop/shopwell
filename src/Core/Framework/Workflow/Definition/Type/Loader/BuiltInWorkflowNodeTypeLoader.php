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
        yield new WorkflowNodeTypeSpecification(DocumentNodeTypes::APPROVAL, 1, WorkflowNodeKind::ACTIVITY, ['in'], ['approved', 'rejected']);
        yield new WorkflowNodeTypeSpecification(DocumentNodeTypes::COPY, 1, WorkflowNodeKind::ACTIVITY, ['in'], ['out']);
        yield new WorkflowNodeTypeSpecification(DocumentNodeTypes::SCRIPT, 1, WorkflowNodeKind::ACTIVITY, ['in'], ['out']);
        yield new WorkflowNodeTypeSpecification(DocumentNodeTypes::ACTION, 1, WorkflowNodeKind::ACTIVITY, ['in'], ['out']);
        yield new WorkflowNodeTypeSpecification(DocumentNodeTypes::NOTIFICATION, 1, WorkflowNodeKind::ACTIVITY, ['in'], ['out']);
        yield new WorkflowNodeTypeSpecification(DocumentNodeTypes::SUBPROCESS, 1, WorkflowNodeKind::ACTIVITY, ['in'], ['out']);
        yield new WorkflowNodeTypeSpecification(DocumentNodeTypes::PARALLEL, 1, WorkflowNodeKind::ACTIVITY, ['in'], ['branch-0', 'branch-1']);
        yield new WorkflowNodeTypeSpecification(DocumentNodeTypes::JOIN, 1, WorkflowNodeKind::ACTIVITY, ['in'], ['out']);
    }
}
