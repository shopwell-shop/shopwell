<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1795148134CreatePaymentTransferApprovalRequests extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795148134;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `payment_transfer_approval_request` (
                `id` BINARY(16) NOT NULL,
                `request_id` BINARY(16) NOT NULL,
                `snapshot` JSON NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_transfer_approval_request.request` (`request_id`),
                CONSTRAINT `fk.payment_transfer_approval_request.transfer` FOREIGN KEY (`id`) REFERENCES `payment_transfer` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk.payment_transfer_approval_request.request` FOREIGN KEY (`request_id`) REFERENCES `approval_flow_request` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `json.payment_transfer_approval_request.snapshot` CHECK (JSON_VALID(`snapshot`))
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
    }
}
