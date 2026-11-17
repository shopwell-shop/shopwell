<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1794846336AddApprovalReminder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794846336;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `workflow_approval_reminder` (
                `id` BINARY(16) NOT NULL,
                `task_id` BINARY(16) NOT NULL,
                `instance_id` BINARY(16) NOT NULL,
                `due_at` DATETIME(3) NULL,
                `remind_on_overdue` TINYINT(1) NOT NULL DEFAULT 0,
                `overdue_reminded_at` DATETIME(3) NULL,
                `last_reminded_at` DATETIME(3) NULL,
                `source` VARCHAR(16) NULL,
                `reminder_count` INT NOT NULL DEFAULT 0,
                `delivery_id` BINARY(16) NULL,
                `delivered_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.workflow_approval_reminder.task` (`task_id`),
                KEY `idx.workflow_approval_reminder.due` (`remind_on_overdue`, `overdue_reminded_at`, `due_at`),
                KEY `idx.workflow_approval_reminder.delivery` (`delivered_at`, `delivery_id`),
                CONSTRAINT `fk.workflow_approval_reminder.task` FOREIGN KEY (`task_id`) REFERENCES `workflow_task` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.workflow_approval_reminder.instance` FOREIGN KEY (`instance_id`) REFERENCES `workflow_instance` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
