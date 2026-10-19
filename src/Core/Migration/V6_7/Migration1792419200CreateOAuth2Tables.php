<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * Creates the tables of the OAuth2 login feature: the admin managed client configuration of the
 * channels and the bindings between the external identities and the customers or admin users.
 *
 * @internal
 */
#[Package('framework')]
class Migration1792419200CreateOAuth2Tables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792419200;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `oauth2_client` (
                `id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(64) NOT NULL,
                `enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `client_id` VARCHAR(255) NULL,
                `client_secret` VARCHAR(255) NULL,
                `scopes` JSON NULL,
                `settings` JSON NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.oauth2_client.technical_name` (`technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `oauth2_identity` (
                `id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(64) NOT NULL,
                `provider_user_id` VARCHAR(255) NOT NULL,
                `customer_id` BINARY(16) NULL,
                `user_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.oauth2_identity.technical_name_provider_user_id` (`technical_name`, `provider_user_id`),
                KEY `idx.oauth2_identity.customer_id` (`customer_id`),
                KEY `idx.oauth2_identity.user_id` (`user_id`),
                CONSTRAINT `fk.oauth2_identity.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.oauth2_identity.user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }
}
