<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1794880115AddApprovalFormChange extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794880115;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `workflow_approval_form_change` (
                `id` BINARY(16) NOT NULL,
                `instance_id` BINARY(16) NOT NULL,
                `task_id` BINARY(16) NULL,
                `round` INT NOT NULL,
                `actor_id` BINARY(16) NULL,
                `revision` INT NOT NULL,
                `changes` JSON NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.workflow_approval_form_change.revision` (`instance_id`, `revision`),
                CONSTRAINT `fk.workflow_approval_form_change.instance` FOREIGN KEY (`instance_id`) REFERENCES `workflow_instance` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.workflow_approval_form_change.task` FOREIGN KEY (`task_id`) REFERENCES `workflow_task` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.workflow_approval_form_change.actor` FOREIGN KEY (`actor_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
