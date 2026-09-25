<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1667983492UpdateQueuedTasksToSkipped extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1667983492;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE `scheduled_task` SET `status` = :skippedStatus, next_execution_time = :nextExecutionTime
                WHERE `status` = :queuedStatus AND `name` IN (:skippedTasks)',
            [
                'skippedStatus' => ScheduledTaskDefinition::STATUS_SKIPPED,
                'queuedStatus' => ScheduledTaskDefinition::STATUS_QUEUED,
                'skippedTasks' => ['shopwell.invalidate_cache', 'shopwell.elasticsearch.create.alias'],
                'nextExecutionTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'skippedTasks' => ArrayParameterType::STRING,
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
