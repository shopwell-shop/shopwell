<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1768986102AddInternalColumnToProductStream extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1768986102;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'product_stream', 'internal')) {
            $this->addColumn($connection, 'product_stream', 'internal', 'TINYINT(1)', false, '0');
        }
    }
}
