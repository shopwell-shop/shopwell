<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Node;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<WorkflowNodeExecutionEntity>
 */
#[Package('framework')]
class WorkflowNodeExecutionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return WorkflowNodeExecutionEntity::class;
    }
}
