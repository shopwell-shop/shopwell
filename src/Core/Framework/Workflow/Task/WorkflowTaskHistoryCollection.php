<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Task;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<WorkflowTaskHistoryEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class WorkflowTaskHistoryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'workflow_task_history_collection';
    }

    protected function getExpectedClass(): string
    {
        return WorkflowTaskHistoryEntity::class;
    }
}
