<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1752750171AddIndexToOrderAddressCreateAndUpdate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1752750171;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::indexExists($connection, 'order_address', 'idx.order_address_created_updated')) {
            $this->executeDdlStatement($connection, 'CREATE INDEX `idx.order_address_created_updated` ON `order_address` (`created_at`, `updated_at`)');
        }
    }
}
