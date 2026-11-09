<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1536232970CustomerAddress extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232970;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `customer_address` (
                `id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NOT NULL,
                `country_id` BINARY(16) NOT NULL,
                `country_state_id` BINARY(16) NULL,
                `country_city_id` BINARY(16) NULL,
                `country_district_id` BINARY(16) NULL,
                `company` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `department` VARCHAR(255) CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `gender` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `street` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `zipcode` VARCHAR(50) COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `city` VARCHAR(70) COLLATE utf8mb4_unicode_ci NOT NULL,
                `vat_id` VARCHAR(50) COLLATE utf8mb4_unicode_ci NULL,
                `phone_number` VARCHAR(40) COLLATE utf8mb4_unicode_ci NULL,
                `additional_address_line1` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `additional_address_line2` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `fk.customer_address.country_id` (`country_id`),
                KEY `fk.customer_address.country_state_id` (`country_state_id`),
                KEY `fk.customer_address.country_city_id` (`country_city_id`),
                KEY `fk.customer_address.country_district_id` (`country_district_id`),
                KEY `fk.customer_address.customer_id` (`customer_id`),
                CONSTRAINT `fk.customer_address.country_city_id` FOREIGN KEY (`country_city_id`) REFERENCES `country_city` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.customer_address.country_district_id` FOREIGN KEY (`country_district_id`) REFERENCES `country_district` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.customer_address.country_id` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.customer_address.country_state_id` FOREIGN KEY (`country_state_id`) REFERENCES `country_state` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.customer_address.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `json.customer_address.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
