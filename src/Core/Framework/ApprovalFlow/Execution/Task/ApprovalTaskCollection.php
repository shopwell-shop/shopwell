<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Task;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ApprovalTaskEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalTaskCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'approval_flow_task_collection';
    }

    protected function getExpectedClass(): string
    {
        return ApprovalTaskEntity::class;
    }
}
