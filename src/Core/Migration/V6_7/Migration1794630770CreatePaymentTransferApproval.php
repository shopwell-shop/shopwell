<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1794630770CreatePaymentTransferApproval extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794630770;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `payment_transfer_approval` (
    `id` BINARY(16) NOT NULL,
    `release_id` BINARY(16) NOT NULL,
    `instance_id` BINARY(16) NOT NULL,
    `initiator_id` BINARY(16) NOT NULL,
    `snapshot` JSON NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.payment_transfer_approval.instance` (`instance_id`),
    CONSTRAINT `json.payment_transfer_approval.snapshot` CHECK (JSON_VALID(`snapshot`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
