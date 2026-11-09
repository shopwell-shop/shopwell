<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1554203706AddImportExportLog extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554203706;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `import_export_log` (
                `id` binary(16) NOT NULL,
                `activity` VARCHAR(255) CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI NOT NULL,
                `state` VARCHAR(255) CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI NOT NULL,
                `records` INT(11) NOT NULL,
                `username` VARCHAR(255) CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `profile_name` VARCHAR(255) CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `user_id` binary(16),
                `profile_id` binary(16),
                `file_id` binary(16),
                `created_at` datetime(3) NOT NULL,
                `updated_at` datetime(3),
                PRIMARY KEY (`id`),
                KEY `fk.import_export_log.user_id` (`user_id`),
                KEY `fk.import_export_log.profile_id` (`profile_id`),
                KEY `fk.import_export_log.file_id` (`file_id`),
                CONSTRAINT `fk.import_export_log.file_id` FOREIGN KEY (`file_id`) REFERENCES `import_export_file` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.import_export_log.profile_id` FOREIGN KEY (`profile_id`) REFERENCES `import_export_profile` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk.import_export_log.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
