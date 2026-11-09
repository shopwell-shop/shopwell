<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1536232600Language extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232600;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `language` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
                `parent_id` BINARY(16) NULL,
                `locale_id` BINARY(16) NOT NULL,
                `translation_code_id` BINARY(16) NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                `translation_auto_update` TINYINT(1) NOT NULL DEFAULT '1',
                PRIMARY KEY (`id`),
                UNIQUE `uniq.translation_code_id` (translation_code_id),
                KEY `idx.language.translation_code_id` (`translation_code_id`),
                KEY `idx.language.language_id_parent_language_id` (`id`, `parent_id`),
                KEY `fk.language.parent_id` (`parent_id`),
                CONSTRAINT `fk.language.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `json.language.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
