<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1773829004RegisterProductStreamIndexer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773829004;
    }

    public function update(Connection $connection): void
    {
        // Similar to \Shopwell\Core\Migration\V6_8\Migration1763125892RemoveProductStatesColumn::update
        // Re-register the rule indexer to ensure product stream which might depend on the removed column are re-indexed properly
        $this->registerIndexer($connection, 'product_stream.indexer');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
