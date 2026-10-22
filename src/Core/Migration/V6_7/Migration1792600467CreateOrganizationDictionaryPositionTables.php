<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\AddColumnTrait;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * Creates the organization domain: the organization with its unit tree, the position catalog and
 * the two mapping tables that attach units and positions to a user. The generic dictionary that
 * the salutation domain was replaced with is created by the V6_3 baseline (and seeded by
 * `BasicData`), so this migration only adds the organization-specific part.
 *
 * An organization is a legal company, its units form a tree (parent, level, materialized path and
 * child count for cheap subtree queries) and each unit is named per language. A position is a
 * flat catalog entry with a sort order and a name, also translated.
 *
 * `user_organization_unit` and `user_position` are the pure mapping tables - the pair of columns
 * is the primary key, no surrogate id. The ids of both mappings are additionally mirrored into the
 * JSON columns `user.organization_unit_ids` / `user.position_ids` by the ManyToManyIdFieldUpdater
 * indexer, so filters on the user listing do not have to join the mapping tables.
 *
 * @internal
 */
#[Package('framework')]
class Migration1792600467CreateOrganizationDictionaryPositionTables extends MigrationStep
{
    use AddColumnTrait;

    public function getCreationTimestamp(): int
    {
        return 1792600467;
    }

    public function update(Connection $connection): void
    {
        $this->createOrganizationTable($connection);
        $this->createOrganizationTranslationTable($connection);
        $this->createOrganizationUnitTable($connection);
        $this->createOrganizationUnitTranslationTable($connection);
        $this->createPositionTable($connection);
        $this->createPositionTranslationTable($connection);
        $this->createUserOrganizationUnitTable($connection);
        $this->createUserPositionTable($connection);
        $this->addUserIdColumns($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }

    /**
     * The domain tables that used to point at the former salutation entity carry a
     * `gender` column (storing the dictionary entry code as a plain string) since their
     * CREATE TABLE (V6_3). No foreign key is required for it.
     */
    private function createOrganizationTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `organization` (
                `id` BINARY(16) NOT NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createOrganizationTranslationTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `organization_translation` (
                `organization_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`organization_id`, `language_id`),
                KEY `fk.organization_translation.language_id` (`language_id`),
                CONSTRAINT `fk.organization_translation.organization_id` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.organization_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createOrganizationUnitTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `organization_unit` (
                `id` BINARY(16) NOT NULL,
                `organization_id` BINARY(16) NOT NULL,
                `parent_id` BINARY(16) NULL,
                `level` INT UNSIGNED NOT NULL DEFAULT 1,
                `path` LONGTEXT NULL,
                `child_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.organization_unit.level` (`level`),
                KEY `fk.organization_unit.organization_id` (`organization_id`),
                KEY `fk.organization_unit.parent_id` (`parent_id`),
                CONSTRAINT `fk.organization_unit.organization_id` FOREIGN KEY (`organization_id`) REFERENCES `organization` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.organization_unit.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `organization_unit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createOrganizationUnitTranslationTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `organization_unit_translation` (
                `organization_unit_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`organization_unit_id`, `language_id`),
                KEY `fk.organization_unit_translation.language_id` (`language_id`),
                CONSTRAINT `fk.organization_unit_translation.organization_unit_id` FOREIGN KEY (`organization_unit_id`) REFERENCES `organization_unit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.organization_unit_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createPositionTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `position` (
                `id` BINARY(16) NOT NULL,
                `position` INT NOT NULL DEFAULT 0,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createPositionTranslationTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `position_translation` (
                `position_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`position_id`, `language_id`),
                KEY `fk.position_translation.language_id` (`language_id`),
                CONSTRAINT `fk.position_translation.position_id` FOREIGN KEY (`position_id`) REFERENCES `position` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.position_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createUserOrganizationUnitTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `user_organization_unit` (
                `user_id` BINARY(16) NOT NULL,
                `organization_unit_id` BINARY(16) NOT NULL,
                `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`user_id`, `organization_unit_id`),
                KEY `fk.user_organization_unit.organization_unit_id` (`organization_unit_id`),
                CONSTRAINT `fk.user_organization_unit.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.user_organization_unit.organization_unit_id` FOREIGN KEY (`organization_unit_id`) REFERENCES `organization_unit` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createUserPositionTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `user_position` (
                `user_id` BINARY(16) NOT NULL,
                `position_id` BINARY(16) NOT NULL,
                PRIMARY KEY (`user_id`, `position_id`),
                KEY `fk.user_position.position_id` (`position_id`),
                CONSTRAINT `fk.user_position.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.user_position.position_id` FOREIGN KEY (`position_id`) REFERENCES `position` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * The JSON mirrors of the two mapping tables, filled by the ManyToManyIdFieldUpdater indexer.
     */
    private function addUserIdColumns(Connection $connection): void
    {
        $this->addColumn($connection, 'user', 'organization_unit_ids', 'JSON');
        $this->addColumn($connection, 'user', 'position_ids', 'JSON');
    }
}
