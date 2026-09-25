<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1767861589AddOpenOffcanvasAfterAddToCartConfigSetting extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1767861589;
    }

    public function update(Connection $connection): void
    {
        $exists = $connection->fetchOne(
            'SELECT id FROM system_config WHERE configuration_key = :key',
            ['key' => 'core.cart.openOffcanvasAfterAddToCart']
        );

        if ($exists) {
            return;
        }

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'core.cart.openOffcanvasAfterAddToCart',
            'configuration_value' => '{"_value": true}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
