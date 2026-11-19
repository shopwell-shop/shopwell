<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1795081112CreateApprovalFlowReleaseTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795081112;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `approval_flow_release` (
                `id` BINARY(16) NOT NULL,
                `definition_id` BINARY(16) NOT NULL,
                `draft_revision` INT UNSIGNED NOT NULL,
                `graph_json` LONGTEXT NOT NULL,
                `contract_json` LONGTEXT NOT NULL,
                `graph_plan_json` LONGTEXT NOT NULL,
                `node_plan_json` LONGTEXT NOT NULL,
                `dependencies_json` LONGTEXT NOT NULL,
                `content_hash` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `created_by_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.approval_flow_release.content` (`definition_id`, `content_hash`),
                CONSTRAINT `fk.approval_flow_release.definition` FOREIGN KEY (`definition_id`) REFERENCES `approval_flow_definition` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk.approval_flow_release.created_by` FOREIGN KEY (`created_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `chk.approval_flow_release.revision` CHECK (`draft_revision` > 0),
                CONSTRAINT `json.approval_flow_release.graph` CHECK (JSON_VALID(`graph_json`)),
                CONSTRAINT `json.approval_flow_release.contract` CHECK (JSON_VALID(`contract_json`)),
                CONSTRAINT `json.approval_flow_release.graph_plan` CHECK (JSON_VALID(`graph_plan_json`)),
                CONSTRAINT `json.approval_flow_release.node_plan` CHECK (JSON_VALID(`node_plan_json`)),
                CONSTRAINT `json.approval_flow_release.dependencies` CHECK (JSON_VALID(`dependencies_json`))
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
    }
}
