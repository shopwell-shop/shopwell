<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1597762808Webhook extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1597762808;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `webhook` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `event_name` VARCHAR(500) NOT NULL,
                `url` VARCHAR(500) NOT NULL,
                `app_id` BINARY(16) NULL,
                `active` TINYINT(1) DEFAULT '1',
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                `error_count` INT NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.webhook.name` (`name`,`app_id`),
                KEY `fk.webhook.app_id` (`app_id`),
                CONSTRAINT `fk.webhook.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}
