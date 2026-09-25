<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1779176196AddFeedLabelToProductExport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1779176196;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'product_export', 'feed_label', 'VARCHAR(20)');
    }
}
