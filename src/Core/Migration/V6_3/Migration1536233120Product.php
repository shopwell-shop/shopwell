<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1536233120Product extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233120;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `product` (
                `id` BINARY(16) NOT NULL,
                `version_id` BINARY(16) NOT NULL,
                `auto_increment` BIGINT unsigned NOT NULL AUTO_INCREMENT,
                `product_number` VARCHAR(64) CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `active` TINYINT UNSIGNED DEFAULT NULL,
                `parent_id` BINARY(16) NULL,
                `parent_version_id` BINARY(16) NULL,
                `tax_id` BINARY(16) NULL,
                `product_manufacturer_id` BINARY(16) NULL,
                `product_manufacturer_version_id` BINARY(16) NULL,
                `product_media_id` BINARY(16) NULL,
                `product_media_version_id` BINARY(16) NULL,
                `unit_id` BINARY(16) NULL,
                `category_tree` JSON NULL,
                `option_ids` JSON NULL,
                `property_ids` JSON NULL,
                `tax` BINARY(16) NULL,
                `manufacturer` BINARY(16) NULL,
                `cover` BINARY(16) NULL,
                `unit` BINARY(16) NULL,
                `media` BINARY(16) NULL,
                `prices` BINARY(16) NULL,
                `visibilities` BINARY(16) NULL,
                `properties` BINARY(16) NULL,
                `categories` BINARY(16) NULL,
                `translations` BINARY(16) NULL,
                `price` JSON NULL,
                `listing_prices` JSON NULL,
                `manufacturer_number` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `ean` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `sales` INT NOT NULL DEFAULT '0',
                `stock` INT NOT NULL,
                `available_stock` INT DEFAULT NULL,
                `available` TINYINT(1) NOT NULL DEFAULT '1',
                `min_delivery_time` INT(11) NULL,
                `max_delivery_time` INT(11) NULL,
                `restock_time` INT(11) NULL,
                `is_closeout` TINYINT(1) NULL,
                `purchase_steps` INT(11) unsigned NULL,
                `max_purchase` INT(11) unsigned NULL,
                `min_purchase` INT(11) unsigned NULL,
                `purchase_unit` DECIMAL(11,4) unsigned NULL,
                `reference_unit` DECIMAL(10,3) unsigned NULL,
                `shipping_free` TINYINT(1) NULL,
                `purchase_price` DOUBLE NULL,
                `mark_as_topseller` TINYINT(1) unsigned NULL,
                `weight` DECIMAL(10,3) unsigned NULL,
                `width` DECIMAL(10,3) unsigned NULL,
                `height` DECIMAL(10,3) unsigned NULL,
                `length` DECIMAL(10,3) unsigned NULL,
                `release_date` DATETIME(3) NULL,
                `whitelist_ids` JSON NULL,
                `blacklist_ids` JSON NULL,
                `tag_ids` JSON NULL,
                `tags` BINARY(16) NULL,
                `variant_restrictions` JSON NULL,
                `configurator_group_sorting` JSON NULL,
                `searchKeywords` BINARY(16) DEFAULT NULL,
                `created_at` DATETIME(3) NULL,
                `updated_at` DATETIME(3) NULL,
                `rating_average` FLOAT DEFAULT NULL,
                `child_count` INT DEFAULT NULL,
                `main_variant_id` BINARY(16) DEFAULT NULL,
                `custom_field_set_selection_active` TINYINT(1) DEFAULT NULL,
                `canonical_product_id` BINARY(16) DEFAULT NULL,
                `canonical_product_version_id` BINARY(16) DEFAULT NULL,
                `canonicalProduct` BINARY(16) DEFAULT NULL,
                `cheapest_price` LONGTEXT CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI,
                `cheapest_price_accessor` LONGTEXT CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI,
                PRIMARY KEY (`id`, `version_id`),
                UNIQUE KEY `uniq.product.product_number__version_id` (`product_number`,`version_id`),
                KEY `idx.auto_increment` (`auto_increment`),
                KEY `fk.product.product_manufacturer` (`product_manufacturer_id`,`product_manufacturer_version_id`),
                KEY `fk.product.tax_id` (`tax_id`),
                KEY `fk.product.unit_id` (`unit_id`),
                KEY `fk.product.parent_id` (`parent_id`,`parent_version_id`),
                KEY `fk.product.canonical_product_id` (`canonical_product_id`,`canonical_product_version_id`),
                CONSTRAINT `fk.product.canonical_product_id` FOREIGN KEY (`canonical_product_id`, `canonical_product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE SET NULL,
                CONSTRAINT `fk.product.parent_id` FOREIGN KEY (`parent_id`, `parent_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.product.product_manufacturer` FOREIGN KEY (`product_manufacturer_id`, `product_manufacturer_version_id`) REFERENCES `product_manufacturer` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.product.tax_id` FOREIGN KEY (`tax_id`) REFERENCES `tax` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.product.unit_id` FOREIGN KEY (`unit_id`) REFERENCES `unit` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `json.product.blacklist_ids` CHECK (JSON_VALID(`blacklist_ids`)),
                CONSTRAINT `json.product.category_tree` CHECK (JSON_VALID(`category_tree`)),
                CONSTRAINT `json.product.configurator_group_sorting` CHECK (JSON_VALID(`configurator_group_sorting`)),
                CONSTRAINT `json.product.listing_prices` CHECK (JSON_VALID(`listing_prices`)),
                CONSTRAINT `json.product.option_ids` CHECK (JSON_VALID(`option_ids`)),
                CONSTRAINT `json.product.price` CHECK (JSON_VALID(`price`)),
                CONSTRAINT `json.product.property_ids` CHECK (JSON_VALID(`property_ids`)),
                CONSTRAINT `json.product.tag_ids` CHECK (JSON_VALID(`tag_ids`)),
                CONSTRAINT `json.product.variant_restrictions` CHECK (JSON_VALID(`variant_restrictions`)),
                CONSTRAINT `json.product.whitelist_ids` CHECK (JSON_VALID(`whitelist_ids`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `product_translation` (
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `additional_text` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `keywords` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NULL,
                `description` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NULL,
                `meta_title` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `pack_unit` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                `pack_unit_plural` VARCHAR(255) CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `search_keywords` JSON DEFAULT NULL,
                PRIMARY KEY (`product_id`, `product_version_id`, `language_id`),
                KEY `fk.product_translation.language_id` (`language_id`),
                CONSTRAINT `fk.product_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.product_translation.product_id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `json.product_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
                CONSTRAINT `json.product_translation.search_keywords` CHECK (JSON_VALID(`search_keywords`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
