<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Definition;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<WorkflowExecutionDefinitionEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class WorkflowExecutionDefinitionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'workflow_execution_definition_collection';
    }

    protected function getExpectedClass(): string
    {
        return WorkflowExecutionDefinitionEntity::class;
    }
}
