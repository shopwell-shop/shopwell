<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Instance;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<WorkflowInstanceEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class WorkflowInstanceCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'workflow_instance_collection';
    }

    protected function getExpectedClass(): string
    {
        return WorkflowInstanceEntity::class;
    }
}
