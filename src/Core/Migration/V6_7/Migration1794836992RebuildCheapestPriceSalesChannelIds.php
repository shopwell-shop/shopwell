<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1794836992RebuildCheapestPriceSalesChannelIds extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794836992;
    }

    public function update(Connection $connection): void
    {
        // rewrites the cheapest price containers, which now store the sales channel ids once per product
        $this->registerIndexer($connection, 'product.indexer', [ProductIndexer::CHEAPEST_PRICE_UPDATER]);
    }
}
