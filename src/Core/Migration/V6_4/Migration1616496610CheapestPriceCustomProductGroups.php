<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1616496610CheapestPriceCustomProductGroups extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1616496610;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE product_stream_filter SET field = "cheapestPrice" WHERE field = "price"');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
