<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1795137733CreateApprovalFlowResultOutbox extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795137733;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `approval_flow_result` (
                `id` BINARY(16) NOT NULL,
                `instance_id` BINARY(16) NOT NULL,
                `release_id` BINARY(16) NOT NULL,
                `command_id` BINARY(16) NOT NULL,
                `aggregate_version` INT UNSIGNED NOT NULL,
                `outcome` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `finished_at` DATETIME(3) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.approval_flow_result.instance` (`instance_id`),
                KEY `idx.approval_flow_result.operation` (`instance_id`, `command_id`),
                CONSTRAINT `fk.approval_flow_result.instance` FOREIGN KEY (`instance_id`) REFERENCES `approval_flow_instance` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk.approval_flow_result.release` FOREIGN KEY (`release_id`) REFERENCES `approval_flow_release` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk.approval_flow_result.operation` FOREIGN KEY (`instance_id`, `command_id`) REFERENCES `approval_flow_operation` (`instance_id`, `command_id`) ON DELETE RESTRICT,
                CONSTRAINT `chk.approval_flow_result.version` CHECK (`aggregate_version` > 0),
                CONSTRAINT `chk.approval_flow_result.outcome` CHECK (`outcome` IN ('approved', 'rejected'))
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `approval_flow_outbox` (
                `result_id` BINARY(16) NOT NULL,
                `status` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `attempt` INT UNSIGNED NOT NULL,
                `next_attempt_at` DATETIME(3) NOT NULL,
                `lease_until` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`result_id`),
                KEY `idx.approval_flow_outbox.due` (`status`, `next_attempt_at`, `result_id`),
                KEY `idx.approval_flow_outbox.lease` (`status`, `lease_until`, `result_id`),
                CONSTRAINT `fk.approval_flow_outbox.result` FOREIGN KEY (`result_id`) REFERENCES `approval_flow_result` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `chk.approval_flow_outbox.status` CHECK (`status` IN ('pending', 'running', 'delivered', 'failed')),
                CONSTRAINT `chk.approval_flow_outbox.lease` CHECK ((`status` = 'running' AND `lease_until` IS NOT NULL) OR (`status` <> 'running' AND `lease_until` IS NULL))
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
    }
}
