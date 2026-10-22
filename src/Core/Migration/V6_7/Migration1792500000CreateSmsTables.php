<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * Creates the tables of the multi gateway sms component: the gateway credentials, the logical
 * templates with their parameter contract and the translated bodies, the mapping from a logical
 * template to the template of a provider account and the record of everything that was sent.
 *
 * @internal
 */
#[Package('framework')]
class Migration1792500000CreateSmsTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792500000;
    }

    public function update(Connection $connection): void
    {
        $this->createSmsGatewayTable($connection);
        $this->createSmsTemplateTable($connection);
        $this->createSmsTemplateTranslationTable($connection);
        $this->createSmsTemplateMappingTable($connection);
        $this->createSmsDeliveryTable($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }

    private function createSmsGatewayTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `sms_gateway` (
                `id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(64) NOT NULL,
                `label` VARCHAR(255) NULL,
                `enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `sales_channel_id` BINARY(16) NULL,
                `priority` INT NOT NULL DEFAULT 0,
                `credentials` JSON NULL,
                `options` JSON NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.sms_gateway.technical_name_channel` (`technical_name`, `sales_channel_id`),
                KEY `idx.sms_gateway.sales_channel_id` (`sales_channel_id`),
                CONSTRAINT `fk.sms_gateway.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createSmsTemplateTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `sms_template` (
                `id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(128) NOT NULL,
                `scene` VARCHAR(64) NOT NULL,
                `params_schema` JSON NULL,
                `max_length` INT NULL,
                `validity_seconds` INT NULL,
                `enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.sms_template.technical_name` (`technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * The body of a template per language, like the one of a mail template. Only the body is
     * translated, the parameter contract and the scene are the same in every language.
     */
    private function createSmsTemplateTranslationTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `sms_template_translation` (
                `sms_template_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `content` LONGTEXT NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`sms_template_id`, `language_id`),
                KEY `fk.sms_template_translation.language_id` (`language_id`),
                CONSTRAINT `fk.sms_template_translation.sms_template_id` FOREIGN KEY (`sms_template_id`) REFERENCES `sms_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.sms_template_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createSmsTemplateMappingTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `sms_template_mapping` (
                `id` BINARY(16) NOT NULL,
                `template_id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(64) NOT NULL,
                `gateway_id` BINARY(16) NULL,
                `template_code` VARCHAR(255) NULL,
                `sign_name` VARCHAR(255) NULL,
                `params_map` JSON NULL,
                `audit_status` VARCHAR(32) NOT NULL DEFAULT \'pending\',
                `reported_content` LONGTEXT NULL,
                `reported_at` DATETIME(3) NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.sms_template_mapping.template_provider_gateway` (`template_id`, `technical_name`, `gateway_id`),
                KEY `idx.sms_template_mapping.gateway_id` (`gateway_id`),
                CONSTRAINT `fk.sms_template_mapping.template_id` FOREIGN KEY (`template_id`) REFERENCES `sms_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.sms_template_mapping.gateway_id` FOREIGN KEY (`gateway_id`) REFERENCES `sms_gateway` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createSmsDeliveryTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `sms_delivery` (
                `id` BINARY(16) NOT NULL,
                `gateway_id` BINARY(16) NULL,
                `technical_name` VARCHAR(64) NOT NULL,
                `phone_masked` VARCHAR(32) NOT NULL,
                `phone_hash` VARCHAR(64) NOT NULL,
                `country_code` VARCHAR(2) NULL,
                `scene` VARCHAR(64) NULL,
                `template_id` BINARY(16) NULL,
                `template_ref` VARCHAR(128) NULL,
                `template_code` VARCHAR(255) NULL,
                `content_hash` VARCHAR(64) NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT \'queued\',
                `provider_message_id` VARCHAR(255) NULL,
                `error_code` VARCHAR(64) NULL,
                `error_message` VARCHAR(512) NULL,
                `segments` INT NULL,
                `sent_at` DATETIME(3) NULL,
                `failed_at` DATETIME(3) NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.sms_delivery.gateway_id` (`gateway_id`),
                KEY `idx.sms_delivery.template_id` (`template_id`),
                KEY `idx.sms_delivery.phone_hash` (`phone_hash`),
                KEY `idx.sms_delivery.status_created_at` (`status`, `created_at`),
                CONSTRAINT `fk.sms_delivery.gateway_id` FOREIGN KEY (`gateway_id`) REFERENCES `sms_gateway` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.sms_delivery.template_id` FOREIGN KEY (`template_id`) REFERENCES `sms_template` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }
}
