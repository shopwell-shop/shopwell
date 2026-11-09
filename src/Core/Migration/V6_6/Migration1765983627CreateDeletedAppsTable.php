<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1765983627CreateDeletedAppsTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1765983627;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `deleted_apps` (
                `name` VARCHAR(255) COLLATE UTF8MB4_UNICODE_CI NOT NULL,
                `app_secret` VARCHAR(255) NOT NULL,
                `unconfirmed_app_secrets` JSON DEFAULT NULL,
                PRIMARY KEY (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }
}
