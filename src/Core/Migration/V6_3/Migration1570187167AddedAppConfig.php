<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1570187167AddedAppConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1570187167;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `app_config` (
                `key` VARCHAR(255) COLLATE UTF8MB4_UNICODE_CI NOT NULL,
                `value` LONGTEXT NOT NULL,
                PRIMARY KEY (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $connection->insert('app_config', [
            '`key`' => 'cache-id',
            '`value`' => Uuid::randomHex(),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
