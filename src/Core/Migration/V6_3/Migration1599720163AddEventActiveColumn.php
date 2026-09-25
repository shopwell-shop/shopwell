<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1599720163AddEventActiveColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1599720163;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `event_action` ADD `active` tinyint(1) NOT NULL DEFAULT 1 AFTER `config`;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
