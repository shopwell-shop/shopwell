<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1775180400AddNextGenerationAtToProductExport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1775180400;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'product_export', 'next_generation_at', 'DATETIME(3)');
    }

    public function updateDestructive(Connection $connection): void
    {
        // Implement updateDestructive() method.
    }
}
