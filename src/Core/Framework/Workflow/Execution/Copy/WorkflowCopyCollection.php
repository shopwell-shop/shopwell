<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Copy;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<WorkflowCopyEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class WorkflowCopyCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'workflow_copy_collection';
    }

    protected function getExpectedClass(): string
    {
        return WorkflowCopyEntity::class;
    }
}
