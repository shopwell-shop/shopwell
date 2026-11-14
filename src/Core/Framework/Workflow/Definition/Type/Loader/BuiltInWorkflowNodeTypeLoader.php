<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Definition\Type\Loader;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeKind;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeTypeSpecification;

/**
 * @internal
 */
#[Package('framework')]
final class BuiltInWorkflowNodeTypeLoader implements WorkflowNodeTypeLoader
{
    public function load(): iterable
    {
        yield new WorkflowNodeTypeSpecification('core:start', 1, WorkflowNodeKind::START, [], ['out']);
        yield new WorkflowNodeTypeSpecification('core:end', 1, WorkflowNodeKind::END, ['in'], []);
        yield new WorkflowNodeTypeSpecification('core:action', 1, WorkflowNodeKind::ACTIVITY, ['in'], ['out']);
        yield new WorkflowNodeTypeSpecification('core:human-task', 1, WorkflowNodeKind::ACTIVITY, ['in'], ['done']);
        yield new WorkflowNodeTypeSpecification('core:approval', 1, WorkflowNodeKind::ACTIVITY, ['in'], ['approved', 'rejected']);
    }
}
