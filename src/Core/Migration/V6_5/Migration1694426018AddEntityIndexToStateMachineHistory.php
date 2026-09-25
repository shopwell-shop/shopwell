<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1694426018AddEntityIndexToStateMachineHistory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1694426018;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'state_machine_history', 'idx.state_machine_history.referenced_entity')) {
            return;
        }

        $connection->executeStatement('
            CREATE INDEX `idx.state_machine_history.referenced_entity`
                ON `state_machine_history` (`referenced_id`, `referenced_version_id`);
        ');
    }
}
