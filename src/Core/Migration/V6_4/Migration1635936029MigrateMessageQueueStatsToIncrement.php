<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1635936029MigrateMessageQueueStatsToIncrement extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1635936029;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            INSERT IGNORE INTO `increment` (`pool`, `cluster`, `key`, `count`, `created_at`, `updated_at`)
            SELECT :pool, :cluster, `name`, `size`, `created_at`, `updated_at` FROM `message_queue_stats`;
        ', [
            'pool' => IncrementGatewayRegistry::MESSAGE_QUEUE_POOL,
            'cluster' => 'message_queue_stats',
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
