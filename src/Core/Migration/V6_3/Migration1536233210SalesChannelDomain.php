<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1536233210SalesChannelDomain extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233210;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `sales_channel_domain` (
                `id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `url` VARCHAR(255) NOT NULL,
                `currency_id` BINARY(16) NOT NULL,
                `snippet_set_id` BINARY(16) NOT NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.sales_channel_domain.url` (`url`),
                KEY `fk.sales_channel_domain.currency_id` (`currency_id`),
                KEY `fk.sales_channel_domain.snippet_set_id` (`snippet_set_id`),
                KEY `fk.sales_channel_domain.language_id` (`language_id`),
                KEY `fk.sales_channel_domain.sales_channel_id` (`sales_channel_id`),
                CONSTRAINT `fk.sales_channel_domain.currency_id` FOREIGN KEY (currency_id) REFERENCES `currency` (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.sales_channel_domain.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.sales_channel_domain.sales_channel_id` FOREIGN KEY (sales_channel_id) REFERENCES `sales_channel` (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.sales_channel_domain.snippet_set_id` FOREIGN KEY (snippet_set_id) REFERENCES `snippet_set` (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `json.sales_channel_domain.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
