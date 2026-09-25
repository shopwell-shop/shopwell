<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1590409548AddPackUnitPluralMigration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1590409548;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `product_translation`
            ADD COLUMN `pack_unit_plural` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
