<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\MessageQueue\ScheduledTask;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ScheduledTaskEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ScheduledTaskCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dal_scheduled_task_collection';
    }

    protected function getExpectedClass(): string
    {
        return ScheduledTaskEntity::class;
    }
}
