<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1783073015McpToolsetSession extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1783073015;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `mcp_toolset_session` (
                `session_id`   VARCHAR(255) NOT NULL,
                `toolset_name` VARCHAR(255) NOT NULL,
                `created_at`   DATETIME(3)  NOT NULL,
                PRIMARY KEY (`session_id`, `toolset_name`),
                KEY `idx_mcp_toolset_session` (`session_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
