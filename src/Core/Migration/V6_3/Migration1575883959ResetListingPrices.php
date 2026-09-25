<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1575883959ResetListingPrices extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1575883959;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE product SET listing_prices = NULL');
        $this->registerIndexer($connection, 'Swag.ProductListingPriceIndexer');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
