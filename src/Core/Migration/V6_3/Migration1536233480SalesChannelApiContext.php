<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1536233480SalesChannelApiContext extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233480;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `sales_channel_api_context` (
                `token` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `payload` JSON NOT NULL,
                `sales_channel_id` BINARY(16) DEFAULT NULL,
                `customer_id` BINARY(16) DEFAULT NULL,
                PRIMARY KEY (`token`),
                UNIQUE KEY `uniq.sales_channel_api_context.sales_channel_id_customer_id` (`sales_channel_id`,`customer_id`),
                KEY `fk.sales_channel_api_context.customer_id` (`customer_id`),
                CONSTRAINT `fk.sales_channel_api_context.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk.sales_channel_api_context.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }
}
