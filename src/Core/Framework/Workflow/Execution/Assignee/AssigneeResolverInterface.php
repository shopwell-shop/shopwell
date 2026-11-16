<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Assignee;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;

/**
 * Resolve ordered, unique user IDs for one assignee type; return no fallback user when resolution is empty.
 *
 * @internal Experimental execution extension point; not covered by the backward compatibility promise.
 */
#[Package('framework')]
interface AssigneeResolverInterface
{
    /**
     * Return the stable execution identifier compiled by the corresponding assignee source.
     */
    public function getAssigneeType(): string;

    /**
     * @return list<string>
     */
    public function resolve(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): array;
}
