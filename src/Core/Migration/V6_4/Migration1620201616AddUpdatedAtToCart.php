<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1620201616AddUpdatedAtToCart extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1620201616;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `cart` ADD COLUMN `updated_at` DATETIME(3) NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
