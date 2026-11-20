<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1795155366CreatePaymentTransferApprovalResults extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795155366;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `payment_transfer_approval_result` (
                `id` BINARY(16) NOT NULL,
                `request_id` BINARY(16) NOT NULL,
                `result_id` BINARY(16) NOT NULL,
                `outcome` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_transfer_approval_result.request` (`request_id`),
                UNIQUE KEY `uniq.payment_transfer_approval_result.result` (`result_id`),
                CONSTRAINT `fk.payment_transfer_approval_result.transfer` FOREIGN KEY (`id`) REFERENCES `payment_transfer` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk.payment_transfer_approval_result.request` FOREIGN KEY (`request_id`) REFERENCES `approval_flow_request` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk.payment_transfer_approval_result.result` FOREIGN KEY (`result_id`) REFERENCES `approval_flow_result` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `chk.payment_transfer_approval_result.outcome` CHECK (`outcome` IN ('approved', 'rejected'))
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
    }
}
