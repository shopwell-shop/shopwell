<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1536232810User extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232810;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `user` (
                `id` BINARY(16) NOT NULL,
                `username` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `password` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `title` VARCHAR(255) CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `email` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `phone_number` VARCHAR(40) COLLATE utf8mb4_unicode_ci NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 0,
                `avatar_id` BINARY(16) NULL,
                `locale_id` BINARY(16) NOT NULL,
                `store_token` VARCHAR(255) NULL,
                `last_updated_password_at` DATETIME(3) DEFAULT NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                `mcp_allowlist` JSON DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.user.email` (`email`),
                UNIQUE KEY `uniq.user.username` (`username`),
                KEY `fk.user.locale_id` (`locale_id`),
                CONSTRAINT `fk.user.locale_id` FOREIGN KEY (`locale_id`) REFERENCES `locale` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `json.user.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
