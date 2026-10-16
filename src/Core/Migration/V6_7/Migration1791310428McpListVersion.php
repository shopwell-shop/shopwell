<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1791310428McpListVersion extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1791310428;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `mcp_list_version` (
                `list`       VARCHAR(32)  NOT NULL,
                `version`    INT UNSIGNED NOT NULL,
                `updated_at` DATETIME(3)  NOT NULL,
                PRIMARY KEY (`list`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
