<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1586260286AddProductMainVariant extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1586260286;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `product`
            ADD `main_variant_id` BINARY(16) NULL
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
