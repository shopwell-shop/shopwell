<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * Moves the configuration of the generic payment gateway from the one dimensional channel model to the
 * interface/channel model.
 *
 * The old model treated every product as its own merchant account: the credentials sat next to the
 * channel - `payment_channel_config` had a `channel_technical_name`, and `payment_app_channel` carried
 * both the credentials and the mode. That made it impossible to add a product of alipay without
 * duplicating the alipay keys, and it structurally allowed an app to use the platform keys for the h5
 * product and its own keys for the next one.
 *
 * The new model puts the merchant account on the payment interface:
 *
 *  - `payment_provider_config` takes over the credentials of `payment_channel_config`,
 *  - `payment_app_provider` takes over the mode and the credentials of `payment_app_channel`,
 *  - `payment_channel_config` keeps the parameters of a channel (`appid` of a wechat channel),
 *  - `payment_app_channel` keeps the switch of a channel for an app, plus its parameter overrides.
 *
 * The interface of a stored row is derived from its channel name, which is what the naming convention
 * of the channels encodes: `alipay_h5` belongs to `alipay`, `wechat_native` to `wechat`, and the two
 * single word providers `paypal` and `stripe` are their own interface.
 *
 * The migration is written to be a no-op on an installation that was created after the tables were
 * restructured, so it is safe to keep in the history.
 *
 * @internal
 */
#[Package('framework')]
class Migration1791856503RestructurePaymentProviderChannel extends MigrationStep
{
    /**
     * The index the old `payment_channel_config` was unique on, and the one it is unique on now.
     */
    private const OLD_CHANNEL_CONFIG_UNIQUE = 'uniq.payment_channel_config.channel';

    private const NEW_CHANNEL_CONFIG_UNIQUE = 'uniq.payment_channel_config.provider_channel';

    public function getCreationTimestamp(): int
    {
        return 1791856503;
    }

    public function update(Connection $connection): void
    {
        $this->createProviderConfigTable($connection);
        $this->createAppProviderTable($connection);

        $this->restructureChannelConfig($connection);
        $this->restructureAppChannel($connection);

        $this->addProviderToOrders($connection);
        $this->addProviderToTransactions($connection);
        $this->renameNotificationColumn($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }

    private function createProviderConfigTable(Connection $connection): void
    {
        if (TableHelper::tableExists($connection, 'payment_provider_config')) {
            return;
        }

        $connection->executeStatement(
            'CREATE TABLE `payment_provider_config` (
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

    private function createAppProviderTable(Connection $connection): void
    {
        if (TableHelper::tableExists($connection, 'payment_app_provider')) {
            return;
        }

        $connection->executeStatement(
            'CREATE TABLE `payment_app_provider` (
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
     * The platform account moves from the channel to the interface, and the column that held it becomes
     * the parameters of the channel.
     */
    private function restructureChannelConfig(Connection $connection): void
    {
        if (!TableHelper::tableExists($connection, 'payment_channel_config')) {
            return;
        }

        $hasCredentials = $this->columnExists($connection, 'payment_channel_config', 'credentials');
        $hasParams = $this->columnExists($connection, 'payment_channel_config', 'params');

        if ($hasCredentials && !$hasParams) {
            $this->executeDdlStatement($connection, 'ALTER TABLE `payment_channel_config` CHANGE COLUMN `credentials` `params` JSON NULL;');
        } elseif (!$hasCredentials && !$hasParams) {
            $this->addColumn($connection, 'payment_channel_config', 'params', 'JSON');
        }

        if (!$this->columnExists($connection, 'payment_channel_config', 'provider_technical_name')) {
            $this->addColumn($connection, 'payment_channel_config', 'provider_technical_name', 'VARCHAR(64)');
        }

        // the interface of a row is the first segment of its channel name
        $connection->executeStatement(
            'UPDATE `payment_channel_config`
                SET `provider_technical_name` = SUBSTRING_INDEX(`channel_technical_name`, \'_\', 1)
                WHERE `provider_technical_name` IS NULL OR `provider_technical_name` = \'\';'
        );

        // what used to be the platform credentials of one channel is the platform account of its
        // interface now - the payload is already sealed with the same cipher, so it can be moved as is
        $this->backfillProviderConfigs($connection);

        $this->executeDdlStatement($connection, 'ALTER TABLE `payment_channel_config` MODIFY COLUMN `provider_technical_name` VARCHAR(64) NOT NULL;');

        $this->dropIndexIfExists($connection, 'payment_channel_config', self::OLD_CHANNEL_CONFIG_UNIQUE);

        if (!$this->indexExists($connection, 'payment_channel_config', self::NEW_CHANNEL_CONFIG_UNIQUE)) {
            $this->executeDdlStatement(
                $connection,
                'ALTER TABLE `payment_channel_config` ADD UNIQUE KEY `' . self::NEW_CHANNEL_CONFIG_UNIQUE . '` (`provider_technical_name`, `channel_technical_name`);'
            );
        }
    }

    /**
     * Copies the platform credentials of the old per channel rows onto their interface - but only for
     * the interfaces that do not have an account yet, so nothing that was configured after the
     * restructure is ever overwritten.
     */
    private function backfillProviderConfigs(Connection $connection): void
    {
        $rows = $connection->fetchAllAssociative(
            'SELECT `provider_technical_name`, `channel_technical_name`, `enabled`, `params`, `settings`
                FROM `payment_channel_config`
                ORDER BY `created_at` ASC'
        );

        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $done = [];

        foreach ($rows as $row) {
            $provider = $row['provider_technical_name'] ?? null;
            $credentials = $row['params'] ?? null;

            if (!\is_string($provider) || $provider === '' || \in_array($provider, $done, true)) {
                continue;
            }

            $done[] = $provider;

            // a channel without credentials never configured the interface - it only has parameters
            if (!\is_string($credentials) || $credentials === '') {
                continue;
            }

            $existing = $connection->fetchOne(
                'SELECT `id` FROM `payment_provider_config` WHERE `provider_technical_name` = :provider',
                ['provider' => $provider]
            );

            if (\is_string($existing) && $existing !== '') {
                continue;
            }

            $connection->insert('payment_provider_config', [
                'id' => Uuid::randomBytes(),
                'provider_technical_name' => $provider,
                'enabled' => (int) ($row['enabled'] ?? 0),
                'credentials' => $credentials,
                'settings' => $row['settings'] ?? null,
                'created_at' => $now,
            ]);

            // the credentials were the account of the interface, the channel itself had no parameters
            $connection->executeStatement(
                'UPDATE `payment_channel_config` SET `params` = NULL, `updated_at` = :now WHERE `provider_technical_name` = :provider',
                ['now' => $now, 'provider' => $provider]
            );
        }
    }

    /**
     * The authorization of an app for a whole interface is separated from the switch of a single
     * channel - the mode and the credentials of the old rows move to the interface.
     */
    private function restructureAppChannel(Connection $connection): void
    {
        if (!TableHelper::tableExists($connection, 'payment_app_channel')) {
            return;
        }

        $hasMode = $this->columnExists($connection, 'payment_app_channel', 'mode');
        $hasCredentials = $this->columnExists($connection, 'payment_app_channel', 'credentials');

        if ($hasMode || $hasCredentials) {
            $rows = $connection->fetchAllAssociative(
                'SELECT `payment_app_id`, `channel_technical_name`, `enabled`, `mode`, `credentials`, `settings`, `created_at`
                    FROM `payment_app_channel`
                    ORDER BY `created_at` ASC'
            );

            foreach ($rows as $row) {
                $provider = $this->providerOf((string) $row['channel_technical_name']);

                $existing = $connection->fetchOne(
                    'SELECT `id` FROM `payment_app_provider` WHERE `payment_app_id` = :app AND `provider_technical_name` = :provider',
                    ['app' => $row['payment_app_id'], 'provider' => $provider]
                );

                if (\is_string($existing) && $existing !== '') {
                    continue;
                }

                $connection->insert('payment_app_provider', [
                    'id' => Uuid::randomBytes(),
                    'payment_app_id' => $row['payment_app_id'],
                    'provider_technical_name' => $provider,
                    'enabled' => (int) $row['enabled'],
                    'mode' => \is_string($row['mode']) && $row['mode'] !== '' ? $row['mode'] : 'platform',
                    'credentials' => $row['credentials'] ?? null,
                    'settings' => $row['settings'] ?? null,
                    'created_at' => $row['created_at'],
                ]);
            }
        }

        if ($hasCredentials) {
            $this->dropColumnIfExists($connection, 'payment_app_channel', 'credentials');
        }

        if ($hasMode) {
            $this->dropColumnIfExists($connection, 'payment_app_channel', 'mode');
        }

        if (!$this->columnExists($connection, 'payment_app_channel', 'params')) {
            $this->addColumn($connection, 'payment_app_channel', 'params', 'JSON');
        }
    }

    /**
     * A payment order remembers both dimensions from now on: the interface that was charged and the
     * channel it was opened with, plus the runtime parameters the channel needed for this payment.
     */
    private function addProviderToOrders(Connection $connection): void
    {
        if (!TableHelper::tableExists($connection, 'payment_order')) {
            return;
        }

        if (!$this->columnExists($connection, 'payment_order', 'provider_technical_name')) {
            $this->addColumn($connection, 'payment_order', 'provider_technical_name', 'VARCHAR(64)');
        }

        $connection->executeStatement(
            'UPDATE `payment_order`
                SET `provider_technical_name` = SUBSTRING_INDEX(`channel_technical_name`, \'_\', 1)
                WHERE `provider_technical_name` IS NULL OR `provider_technical_name` = \'\';'
        );

        $this->executeDdlStatement($connection, 'ALTER TABLE `payment_order` MODIFY COLUMN `provider_technical_name` VARCHAR(64) NOT NULL;');

        if (!$this->columnExists($connection, 'payment_order', 'channel_params')) {
            $this->addColumn($connection, 'payment_order', 'channel_params', 'JSON');
        }

        if (!$this->indexExists($connection, 'payment_order', 'idx.payment_order.provider')) {
            $this->executeDdlStatement($connection, 'ALTER TABLE `payment_order` ADD KEY `idx.payment_order.provider` (`provider_technical_name`);');
        }
    }

    /**
     * Each attempt of a payment records the interface it was charged on, next to the channel.
     */
    private function addProviderToTransactions(Connection $connection): void
    {
        if (!TableHelper::tableExists($connection, 'payment_transaction')) {
            return;
        }

        if (!$this->columnExists($connection, 'payment_transaction', 'provider_technical_name')) {
            $this->addColumn($connection, 'payment_transaction', 'provider_technical_name', 'VARCHAR(64)');
        }

        $connection->executeStatement(
            'UPDATE `payment_transaction`
                SET `provider_technical_name` = SUBSTRING_INDEX(`channel_technical_name`, \'_\', 1)
                WHERE `provider_technical_name` IS NULL OR `provider_technical_name` = \'\';'
        );

        $this->executeDdlStatement($connection, 'ALTER TABLE `payment_transaction` MODIFY COLUMN `provider_technical_name` VARCHAR(64) NOT NULL;');
    }

    /**
     * A notification is signed by the interface and not by a channel, so the column is renamed.
     */
    private function renameNotificationColumn(Connection $connection): void
    {
        if (!TableHelper::tableExists($connection, 'payment_notification')) {
            return;
        }

        if (
            !$this->columnExists($connection, 'payment_notification', 'channel_technical_name')
            || $this->columnExists($connection, 'payment_notification', 'provider_technical_name')
        ) {
            return;
        }

        $this->executeDdlStatement(
            $connection,
            'ALTER TABLE `payment_notification` CHANGE COLUMN `channel_technical_name` `provider_technical_name` VARCHAR(64) NOT NULL;'
        );

        $this->dropIndexIfExists($connection, 'payment_notification', 'idx.payment_notification.channel');

        if (!$this->indexExists($connection, 'payment_notification', 'idx.payment_notification.provider')) {
            $this->executeDdlStatement($connection, 'ALTER TABLE `payment_notification` ADD KEY `idx.payment_notification.provider` (`provider_technical_name`);');
        }
    }

    /**
     * The interface of a channel is the first segment of its name, which is the naming convention every
     * declaration follows: `alipay_h5` belongs to `alipay`, `paypal` is its own interface.
     */
    private function providerOf(string $channel): string
    {
        $separator = strpos($channel, '_');

        return $separator === false ? $channel : substr($channel, 0, $separator);
    }
}
