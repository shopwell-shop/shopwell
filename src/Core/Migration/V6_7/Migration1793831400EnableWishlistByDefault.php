<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * Enable the global wishlist default.
 *
 * Only the global row is touched - a value a merchant set for one sales channel keeps winning over
 * the default, exactly like before.
 *
 * @internal
 */
#[Package('framework')]
class Migration1793831400EnableWishlistByDefault extends MigrationStep
{
    final public const CONFIG_KEY = 'core.cart.wishlistEnabled';

    public function getCreationTimestamp(): int
    {
        return 1793831400;
    }

    public function update(Connection $connection): void
    {
        $globalRowId = $connection->fetchOne('
            SELECT id
            FROM system_config
            WHERE configuration_key = :configurationKey
                AND sales_channel_id IS NULL;
        ', ['configurationKey' => self::CONFIG_KEY]);

        if ($globalRowId) {
            $connection->update('system_config', [
                'configuration_value' => json_encode(['_value' => true], \JSON_THROW_ON_ERROR),
                'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], [
                'id' => $globalRowId,
            ]);

            return;
        }

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::CONFIG_KEY,
            'configuration_value' => json_encode(['_value' => true], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
