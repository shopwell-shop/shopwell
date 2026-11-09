<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1536233280CustomFieldSet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233280;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `custom_field_set` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `config` JSON NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `position` INT NOT NULL DEFAULT '1',
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3),
                `extension_name` VARCHAR(255) COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `json.custom_field_set.config` CHECK(JSON_VALID(`config`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
