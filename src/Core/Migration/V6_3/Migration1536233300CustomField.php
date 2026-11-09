<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1536233300CustomField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233300;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `custom_field` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `type` VARCHAR(255) NOT NULL,
                `config` JSON NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `set_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                `allow_customer_write` TINYINT NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.custom_field.name` (`name`),
                KEY `fk.custom_field.set_id` (`set_id`),
                CONSTRAINT `fk.custom_field.set_id` FOREIGN KEY (set_id) REFERENCES `custom_field_set` (id) ON UPDATE CASCADE ON DELETE CASCADE,
                CONSTRAINT `json.custom_field.config` CHECK(JSON_VALID(`config`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
