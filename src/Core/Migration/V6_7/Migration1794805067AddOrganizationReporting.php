<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\AddColumnTrait;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Organization\OrganizationDefaults;

/**
 * @internal
 */
#[Package('framework')]
class Migration1794805067AddOrganizationReporting extends MigrationStep
{
    use AddColumnTrait;

    public function getCreationTimestamp(): int
    {
        return 1794805067;
    }

    public function update(Connection $connection): void
    {
        $this->addReportingFields($connection);
        $this->addAssignmentTimestamps($connection, 'user_organization_unit');
        $this->seedOrganization($connection);
        $this->assignMissingDepartments($connection);
        $this->scopePositionAssignments($connection);
        $this->assignMissingPositions($connection);
        $this->refreshAssignmentIds($connection);
        $this->addAdditionalPrivileges($connection, [
            'user.viewer' => ['user_position:read'],
            'user.editor' => ['user_position:read', 'user_position:create', 'user_position:update', 'user_position:delete'],
            'user.creator' => ['user_position:read', 'user_position:create', 'user_position:update', 'user_position:delete'],
        ]);
    }

    /**
     * @param non-empty-string $table
     */
    private function addAssignmentTimestamps(Connection $connection, string $table): void
    {
        $this->addColumn($connection, $table, 'created_at', 'DATETIME(3)');
        $this->addColumn($connection, $table, 'updated_at', 'DATETIME(3)');
        $connection->executeStatement('UPDATE `' . $table . '` SET `created_at` = NOW(3) WHERE `created_at` IS NULL');

        if (!TableHelper::getColumnOfTable($connection, $table, 'created_at')->isNotNull) {
            $connection->executeStatement('ALTER TABLE `' . $table . '` MODIFY `created_at` DATETIME(3) NOT NULL');
        }
    }

    private function addReportingFields(Connection $connection): void
    {
        $this->addColumn($connection, 'user_organization_unit', 'is_leader', 'TINYINT(1)', false, '0');
        $this->addColumn($connection, 'user_organization_unit', 'leader_position', 'INT', false, '0');
        $this->addColumn($connection, 'user_organization_unit', 'supervisor_membership_id', 'BINARY(16)');

        if (TableHelper::foreignKeyExists($connection, 'user_organization_unit', 'fk.user_organization_unit.supervisor_membership_id')) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `user_organization_unit`
            ADD CONSTRAINT `fk.user_organization_unit.supervisor_membership_id`
            FOREIGN KEY (`supervisor_membership_id`) REFERENCES `user_organization_unit` (`id`)
            ON DELETE SET NULL ON UPDATE CASCADE');
    }

    private function seedOrganization(Connection $connection): void
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $organizationId = Uuid::fromHexToBytes(OrganizationDefaults::ORGANIZATION);
        $unitId = Uuid::fromHexToBytes(OrganizationDefaults::UNIT);
        $positionId = Uuid::fromHexToBytes(OrganizationDefaults::POSITION);

        $connection->executeStatement(
            'INSERT IGNORE INTO `organization` (`id`, `active`, `created_at`) VALUES (:id, 1, :createdAt)',
            ['id' => $organizationId, 'createdAt' => $now],
        );
        $connection->executeStatement(
            'INSERT IGNORE INTO `organization_unit` (`id`, `organization_id`, `active`, `created_at`)
             VALUES (:id, :organizationId, 1, :createdAt)',
            ['id' => $unitId, 'organizationId' => $organizationId, 'createdAt' => $now],
        );
        $connection->executeStatement(
            'INSERT IGNORE INTO `position` (`id`, `active`, `position`, `created_at`) VALUES (:id, 1, 0, :createdAt)',
            ['id' => $positionId, 'createdAt' => $now],
        );

        $this->seedTranslations($connection, 'organization', $organizationId, 'Shopwell', 'Shopwell', $now);
        $this->seedTranslations($connection, 'organization_unit', $unitId, '总部', 'Headquarters', $now);
        $this->seedTranslations($connection, 'position', $positionId, '员工', 'Employee', $now);
    }

    private function seedTranslations(Connection $connection, string $entity, string $id, string $chinese, string $english, string $createdAt): void
    {
        $connection->executeStatement(
            'INSERT IGNORE INTO `' . $entity . '_translation` (`' . $entity . '_id`, `language_id`, `name`, `created_at`)
             SELECT :id, `language`.`id`, IF(`locale`.`code` LIKE \'zh%\', :chinese, :english), :createdAt
             FROM `language` INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`',
            ['id' => $id, 'chinese' => $chinese, 'english' => $english, 'createdAt' => $createdAt],
        );
    }

    private function assignMissingDepartments(Connection $connection): void
    {
        $connection->executeStatement(
            'INSERT INTO `user_organization_unit` (`id`, `user_id`, `organization_unit_id`, `is_primary`, `created_at`)
             SELECT UNHEX(SUBSTRING(SHA2(CONCAT(\'default-department:\', HEX(`user`.`id`)), 256), 1, 32)), `user`.`id`, :unitId, 1, NOW(3)
             FROM `user`
             WHERE (`user`.`admin` = 0 OR EXISTS (SELECT 1 FROM `user_position` WHERE `user_id` = `user`.`id`))
               AND NOT EXISTS (SELECT 1 FROM `user_organization_unit` WHERE `user_id` = `user`.`id`)',
            ['unitId' => Uuid::fromHexToBytes(OrganizationDefaults::UNIT)],
        );

        // Preserve a primary assignment when one exists; otherwise select a stable existing membership.
        $connection->executeStatement(
            'UPDATE `user_organization_unit` AS membership
             INNER JOIN (
                 SELECT `user_id`, COALESCE(MIN(IF(`is_primary` = 1, `id`, NULL)), MIN(`id`)) AS primary_id
                 FROM `user_organization_unit` GROUP BY `user_id`
             ) AS chosen ON chosen.user_id = membership.user_id
             SET membership.is_primary = (membership.id = chosen.primary_id)',
        );
    }

    private function scopePositionAssignments(Connection $connection): void
    {
        $this->addAssignmentTimestamps($connection, 'user_position');
        $this->addColumn($connection, 'user_position', 'id', 'BINARY(16)');
        $this->addColumn($connection, 'user_position', 'organization_unit_id', 'BINARY(16)');
        $this->addColumn($connection, 'user_position', 'is_primary', 'TINYINT(1)', false, '0');

        $connection->executeStatement(
            'UPDATE `user_position` AS assignment
             INNER JOIN `user_organization_unit` AS membership ON membership.user_id = assignment.user_id AND membership.is_primary = 1
             SET assignment.id = COALESCE(assignment.id, UNHEX(SUBSTRING(SHA2(CONCAT(HEX(assignment.user_id), HEX(assignment.position_id)), 256), 1, 32))),
                 assignment.organization_unit_id = COALESCE(assignment.organization_unit_id, membership.organization_unit_id)
             WHERE assignment.id IS NULL OR assignment.organization_unit_id IS NULL',
        );

        $primaryColumns = $connection->fetchFirstColumn(
            'SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'user_position\' AND CONSTRAINT_NAME = \'PRIMARY\' ORDER BY ORDINAL_POSITION',
        );
        if ($primaryColumns !== ['id']) {
            $connection->executeStatement('ALTER TABLE `user_position`
                ADD INDEX `fk.user_position.user_id` (`user_id`),
                DROP PRIMARY KEY,
                MODIFY `id` BINARY(16) NOT NULL,
                MODIFY `organization_unit_id` BINARY(16) NOT NULL,
                ADD PRIMARY KEY (`id`),
                ADD UNIQUE KEY `uniq.user_position.assignment` (`user_id`, `position_id`, `organization_unit_id`)');
        }

        if (TableHelper::foreignKeyExists($connection, 'user_position', 'fk.user_position.membership')) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `user_position`
            ADD CONSTRAINT `fk.user_position.membership` FOREIGN KEY (`user_id`, `organization_unit_id`)
            REFERENCES `user_organization_unit` (`user_id`, `organization_unit_id`) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    private function refreshAssignmentIds(Connection $connection): void
    {
        foreach ([
            'user_organization_unit' => ['organization_unit_id', 'organization_unit_ids'],
            'user_position' => ['position_id', 'position_ids'],
        ] as $table => [$referenceColumn, $idsColumn]) {
            $connection->executeStatement(
                'UPDATE `user`
                 LEFT JOIN (
                     SELECT user_id, JSON_ARRAYAGG(assignment_id) AS ids FROM (
                         SELECT DISTINCT user_id, LOWER(HEX(`' . $referenceColumn . '`)) AS assignment_id
                         FROM `' . $table . '`
                     ) AS unique_assignments GROUP BY user_id
                 ) AS assignments ON assignments.user_id = `user`.id
                 SET `user`.`' . $idsColumn . '` = COALESCE(assignments.ids, JSON_ARRAY())',
            );
        }
    }

    private function assignMissingPositions(Connection $connection): void
    {
        $connection->executeStatement(
            'INSERT INTO `user_position` (`id`, `user_id`, `position_id`, `organization_unit_id`, `is_primary`, `created_at`)
             SELECT UNHEX(SUBSTRING(SHA2(CONCAT(\'default-position:\', HEX(`user`.`id`)), 256), 1, 32)), `user`.`id`, :positionId, membership.organization_unit_id, 1, NOW(3)
             FROM `user`
             INNER JOIN `user_organization_unit` AS membership ON membership.user_id = `user`.`id` AND membership.is_primary = 1
             WHERE `user`.`admin` = 0 AND NOT EXISTS (SELECT 1 FROM `user_position` WHERE `user_id` = `user`.`id`)',
            ['positionId' => Uuid::fromHexToBytes(OrganizationDefaults::POSITION)],
        );
        $connection->executeStatement(
            'UPDATE `user_position` AS assignment
             INNER JOIN (
                 SELECT `user_id`, COALESCE(MIN(IF(`is_primary` = 1, `id`, NULL)), MIN(`id`)) AS primary_id
                 FROM `user_position` GROUP BY `user_id`
             ) AS chosen ON chosen.user_id = assignment.user_id
             SET assignment.is_primary = (assignment.id = chosen.primary_id)',
        );
    }
}
