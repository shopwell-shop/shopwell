<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * Creates the table of the top-ups a customer pays online.
 *
 * The row is the intent of the customer, the payment order of the gateway is the attempt: a customer
 * who closes the payment interface and tries again gets a second row, while a paid row is credited
 * exactly once. The state machine of the row therefore carries the whole settlement.
 *
 * @internal
 */
#[Package('framework')]
class Migration1792600010CreateWalletTopUpTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792600010;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `customer_wallet_top_up` (
                `id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NOT NULL,
                `currency_id` BINARY(16) NOT NULL,
                `currency` VARCHAR(3) NOT NULL,
                `amount` DOUBLE NOT NULL DEFAULT 0,
                `state` VARCHAR(32) NOT NULL,
                `payment_no` VARCHAR(64) NULL,
                `provider_technical_name` VARCHAR(64) NULL,
                `channel_technical_name` VARCHAR(64) NULL,
                `paid_at` DATETIME(3) NULL,
                `credited_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.customer_wallet_top_up.customer_id` (`customer_id`),
                KEY `idx.customer_wallet_top_up.currency_id` (`currency_id`),
                KEY `idx.customer_wallet_top_up.payment_no` (`payment_no`),
                KEY `idx.customer_wallet_top_up.state` (`state`),
                CONSTRAINT `fk.customer_wallet_top_up.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.customer_wallet_top_up.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }
}
