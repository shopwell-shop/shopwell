<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1702982372FixProductCrossSellingSortByPrice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1702982372;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE product_cross_selling SET sort_by = "cheapestPrice" WHERE sort_by = "price"');
    }
}
