<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * Creates the tables of the generic payment gateway.
 *
 * The configuration of the gateway is a 2x2 matrix, and the tables follow it exactly:
 *
 *  - `payment_provider_config` is the platform account of a payment interface (alipay, wechat pay, …),
 *  - `payment_app_provider` is the authorization of an app for a whole interface, and it carries the
 *    mode - the platform account or the account the app brings itself,
 *  - `payment_channel_config` are the parameters of one channel of an interface (the appid of a wechat
 *    channel, for instance),
 *  - `payment_app_channel` is the switch of one channel for one app.
 *
 * The credentials live on the interface and never on a single product of it: an alipay application
 * covers every product of alipay, so the mobile web payment and the qr code payment share the very same
 * keys. Adding a product therefore never duplicates a key.
 *
 * The rest of the tables is the bookkeeping: the payment orders, their attempts, the refunds, the
 * incoming notifications and the outgoing webhook deliveries.
 *
 * @internal
 */
#[Package('framework')]
class Migration1791856501CreatePaymentTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1791856501;
    }

    public function update(Connection $connection): void
    {
        $this->createPaymentAppTable($connection);
        $this->createPaymentProviderConfigTable($connection);
        $this->createPaymentChannelConfigTable($connection);
        $this->createPaymentAppProviderTable($connection);
        $this->createPaymentAppChannelTable($connection);
        $this->createPaymentOrderTable($connection);
        $this->createPaymentTransactionTable($connection);
        $this->createPaymentRefundTable($connection);
        $this->createPaymentNotificationTable($connection);
        $this->createPaymentWebhookDeliveryTable($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }

    private function createPaymentAppTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_app` (
                `id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(64) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `app_secret` VARCHAR(255) NOT NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `notify_url` VARCHAR(1024) NULL,
                `return_url` VARCHAR(1024) NULL,
                `allowed_ips` JSON NULL,
                `rate_limit` INT NULL,
                `settings` JSON NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_app.technical_name` (`technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * The platform account of an interface. One row per interface and not per channel: rotating a key
     * is a single row, and no product of the interface can end up with a different key than its sibling.
     */
    private function createPaymentProviderConfigTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_provider_config` (
                `id` BINARY(16) NOT NULL,
                `provider_technical_name` VARCHAR(64) NOT NULL,
                `enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `credentials` JSON NULL,
                `settings` JSON NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_provider_config.provider` (`provider_technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * The parameters of one channel of an interface, the appid of a wechat channel for instance. They
     * belong to the channel and not to the interface, because the h5 channel and the jsapi channel of
     * wechat are bound to different appids of the very same merchant account.
     */
    private function createPaymentChannelConfigTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_channel_config` (
                `id` BINARY(16) NOT NULL,
                `provider_technical_name` VARCHAR(64) NOT NULL,
                `channel_technical_name` VARCHAR(64) NOT NULL,
                `enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `params` JSON NULL,
                `settings` JSON NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_channel_config.provider_channel` (`provider_technical_name`, `channel_technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * Which interface an app may collect through, and whose merchant account is charged.
     */
    private function createPaymentAppProviderTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_app_provider` (
                `id` BINARY(16) NOT NULL,
                `payment_app_id` BINARY(16) NOT NULL,
                `provider_technical_name` VARCHAR(64) NOT NULL,
                `enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `mode` VARCHAR(32) NOT NULL DEFAULT \'platform\',
                `credentials` JSON NULL,
                `settings` JSON NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_app_provider.app_provider` (`payment_app_id`, `provider_technical_name`),
                KEY `idx.payment_app_provider.provider` (`provider_technical_name`),
                CONSTRAINT `fk.payment_app_provider.payment_app_id` FOREIGN KEY (`payment_app_id`) REFERENCES `payment_app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * Which channels of those interfaces the app offers to its own users. A channel is only usable when
     * its interface is authorized as well, which is why the two authorizations are two tables.
     */
    private function createPaymentAppChannelTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_app_channel` (
                `id` BINARY(16) NOT NULL,
                `payment_app_id` BINARY(16) NOT NULL,
                `channel_technical_name` VARCHAR(64) NOT NULL,
                `enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `params` JSON NULL,
                `settings` JSON NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_app_channel.app_channel` (`payment_app_id`, `channel_technical_name`),
                KEY `idx.payment_app_channel.channel` (`channel_technical_name`),
                CONSTRAINT `fk.payment_app_channel.payment_app_id` FOREIGN KEY (`payment_app_id`) REFERENCES `payment_app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createPaymentOrderTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_order` (
                `id` BINARY(16) NOT NULL,
                `payment_no` VARCHAR(64) NOT NULL,
                `out_trade_no` VARCHAR(128) NOT NULL,
                `payment_app_id` BINARY(16) NOT NULL,
                `provider_technical_name` VARCHAR(64) NOT NULL,
                `channel_technical_name` VARCHAR(64) NOT NULL,
                `channel_params` JSON NULL,
                `amount` INT NOT NULL,
                `paid_amount` INT NOT NULL DEFAULT 0,
                `refunded_amount` INT NOT NULL DEFAULT 0,
                `currency` VARCHAR(3) NOT NULL,
                `subject` VARCHAR(255) NOT NULL,
                `body` VARCHAR(512) NULL,
                `attach` VARCHAR(1024) NULL,
                `state_id` BINARY(16) NOT NULL,
                `out_payment_no` VARCHAR(128) NULL,
                `channel_data` JSON NULL,
                `client_ip` VARCHAR(45) NULL,
                `device` VARCHAR(32) NULL,
                `notify_url` VARCHAR(1024) NULL,
                `return_url` VARCHAR(1024) NULL,
                `idempotency_key` VARCHAR(128) NULL,
                `expire_at` DATETIME(3) NULL,
                `paid_at` DATETIME(3) NULL,
                `closed_at` DATETIME(3) NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_order.payment_no` (`payment_no`),
                UNIQUE KEY `uniq.payment_order.app_out_trade_no` (`payment_app_id`, `out_trade_no`),
                UNIQUE KEY `uniq.payment_order.app_idempotency` (`payment_app_id`, `idempotency_key`),
                KEY `idx.payment_order.provider` (`provider_technical_name`),
                KEY `idx.payment_order.channel` (`channel_technical_name`),
                KEY `idx.payment_order.state_id` (`state_id`),
                KEY `idx.payment_order.paid_at` (`paid_at`),
                KEY `idx.payment_order.expire_at` (`expire_at`),
                CONSTRAINT `fk.payment_order.payment_app_id` FOREIGN KEY (`payment_app_id`) REFERENCES `payment_app` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_order.state_id` FOREIGN KEY (`state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createPaymentTransactionTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_transaction` (
                `id` BINARY(16) NOT NULL,
                `payment_order_id` BINARY(16) NOT NULL,
                `transaction_no` VARCHAR(64) NOT NULL,
                `type` VARCHAR(32) NOT NULL,
                `provider_technical_name` VARCHAR(64) NOT NULL,
                `channel_technical_name` VARCHAR(64) NOT NULL,
                `request_payload` JSON NULL,
                `response_payload` JSON NULL,
                `out_transaction_id` VARCHAR(128) NULL,
                `status` VARCHAR(32) NOT NULL,
                `error_code` VARCHAR(64) NULL,
                `error_message` VARCHAR(512) NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_transaction.transaction_no` (`transaction_no`),
                KEY `idx.payment_transaction.payment_order_id` (`payment_order_id`),
                CONSTRAINT `fk.payment_transaction.payment_order_id` FOREIGN KEY (`payment_order_id`) REFERENCES `payment_order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createPaymentRefundTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_refund` (
                `id` BINARY(16) NOT NULL,
                `refund_no` VARCHAR(64) NOT NULL,
                `payment_order_id` BINARY(16) NOT NULL,
                `out_refund_no` VARCHAR(128) NOT NULL,
                `amount` INT NOT NULL,
                `currency` VARCHAR(3) NOT NULL,
                `reason` VARCHAR(255) NULL,
                `state_id` BINARY(16) NOT NULL,
                `out_refund_id` VARCHAR(128) NULL,
                `refunded_at` DATETIME(3) NULL,
                `request_payload` JSON NULL,
                `response_payload` JSON NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_refund.refund_no` (`refund_no`),
                UNIQUE KEY `uniq.payment_refund.order_out_refund_no` (`payment_order_id`, `out_refund_no`),
                KEY `idx.payment_refund.state_id` (`state_id`),
                CONSTRAINT `fk.payment_refund.payment_order_id` FOREIGN KEY (`payment_order_id`) REFERENCES `payment_order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_refund.state_id` FOREIGN KEY (`state_id`) REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * A notification is attributed to the interface that sent it and not to a channel: it is the
     * interface that signs - or encrypts - the body, so its credentials are the ones that read it.
     */
    private function createPaymentNotificationTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_notification` (
                `id` BINARY(16) NOT NULL,
                `payment_order_id` BINARY(16) NULL,
                `provider_technical_name` VARCHAR(64) NOT NULL,
                `raw_body` LONGTEXT NULL,
                `headers` JSON NULL,
                `signature_valid` TINYINT(1) NOT NULL DEFAULT 0,
                `processed` TINYINT(1) NOT NULL DEFAULT 0,
                `process_result` VARCHAR(32) NULL,
                `error_message` VARCHAR(512) NULL,
                `remote_ip` VARCHAR(45) NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.payment_notification.payment_order_id` (`payment_order_id`),
                KEY `idx.payment_notification.provider` (`provider_technical_name`),
                KEY `idx.payment_notification.processed` (`processed`),
                CONSTRAINT `fk.payment_notification.payment_order_id` FOREIGN KEY (`payment_order_id`) REFERENCES `payment_order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    private function createPaymentWebhookDeliveryTable(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `payment_webhook_delivery` (
                `id` BINARY(16) NOT NULL,
                `payment_order_id` BINARY(16) NOT NULL,
                `payment_app_id` BINARY(16) NOT NULL,
                `event` VARCHAR(64) NOT NULL,
                `url` VARCHAR(1024) NOT NULL,
                `payload` JSON NULL,
                `signature` VARCHAR(255) NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT \'pending\',
                `attempt_count` INT NOT NULL DEFAULT 0,
                `next_retry_at` DATETIME(3) NULL,
                `last_http_status` INT NULL,
                `last_response` LONGTEXT NULL,
                `succeeded_at` DATETIME(3) NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.payment_webhook_delivery.order_event` (`payment_order_id`, `event`),
                KEY `idx.payment_webhook_delivery.retry` (`status`, `next_retry_at`),
                KEY `idx.payment_webhook_delivery.payment_app_id` (`payment_app_id`),
                CONSTRAINT `fk.payment_webhook_delivery.payment_order_id` FOREIGN KEY (`payment_order_id`) REFERENCES `payment_order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.payment_webhook_delivery.payment_app_id` FOREIGN KEY (`payment_app_id`) REFERENCES `payment_app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }
}
