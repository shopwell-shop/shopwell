<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Condition;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Type\Loader\WorkflowNodeTypeLoader;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeKind;
use Shopwell\Core\Framework\Workflow\Definition\Type\Specification\WorkflowNodeTypeSpecification;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowNodeTypes as DocumentNodeTypes;

/**
 * @internal
 */
#[Package('framework')]
final class ConditionWorkflowNodeLoader implements WorkflowNodeTypeLoader
{
    public function load(): iterable
    {
        yield new WorkflowNodeTypeSpecification(DocumentNodeTypes::CONDITION, 1, WorkflowNodeKind::ACTIVITY, ['in'], ['branch-1', 'fallback']);
    }
}
