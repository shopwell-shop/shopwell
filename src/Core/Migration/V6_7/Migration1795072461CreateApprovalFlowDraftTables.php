<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1795072461CreateApprovalFlowDraftTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795072461;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `approval_flow_definition` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `created_by_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.approval_flow_definition.created_by_id` FOREIGN KEY (`created_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `approval_flow_draft` (
                `definition_id` BINARY(16) NOT NULL,
                `revision` INT UNSIGNED NOT NULL,
                `graph_json` LONGTEXT NOT NULL,
                `updated_by_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`definition_id`),
                CONSTRAINT `fk.approval_flow_draft.definition_id` FOREIGN KEY (`definition_id`) REFERENCES `approval_flow_definition` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk.approval_flow_draft.updated_by_id` FOREIGN KEY (`updated_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `chk.approval_flow_draft.revision` CHECK (`revision` > 0),
                CONSTRAINT `json.approval_flow_draft.graph_json` CHECK (JSON_VALID(`graph_json`))
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
    }
}
