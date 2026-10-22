<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * Creates the generic data dictionary: a `dictionary_type` groups entries by purpose (the
 * salutation form-of-address, and whatever a plugin needs next), every entry carries a technical
 * name that is unique per type plus an ordering and its translations. Other domains reference
 * entries by id, so renaming a label never rewrites business data.
 *
 * The tables live in the baseline ahead of the basic data migration, so `BasicData` can seed the
 * default salutation entries right where the rest of the install defaults are written.
 *
 * @internal
 */
#[Package('framework')]
class Migration1536232950CreateDictionaryTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232950;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `dictionary_type` (
                `id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.dictionary_type.technical_name` (`technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `dictionary_type_translation` (
                `dictionary_type_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `label` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`dictionary_type_id`, `language_id`),
                KEY `fk.dictionary_type_translation.language_id` (`language_id`),
                CONSTRAINT `fk.dictionary_type_translation.dictionary_type_id` FOREIGN KEY (`dictionary_type_id`) REFERENCES `dictionary_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.dictionary_type_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `dictionary_entry` (
                `id` BINARY(16) NOT NULL,
                `dictionary_type_id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(255) NOT NULL,
                `position` INT NOT NULL DEFAULT 100,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.dictionary_entry.technical_name` (`dictionary_type_id`, `technical_name`),
                KEY `fk.dictionary_entry.dictionary_type_id` (`dictionary_type_id`),
                CONSTRAINT `fk.dictionary_entry.dictionary_type_id` FOREIGN KEY (`dictionary_type_id`) REFERENCES `dictionary_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `dictionary_entry_translation` (
                `dictionary_entry_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `label` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`dictionary_entry_id`, `language_id`),
                KEY `fk.dictionary_entry_translation.language_id` (`language_id`),
                CONSTRAINT `fk.dictionary_entry_translation.dictionary_entry_id` FOREIGN KEY (`dictionary_entry_id`) REFERENCES `dictionary_entry` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.dictionary_entry_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
