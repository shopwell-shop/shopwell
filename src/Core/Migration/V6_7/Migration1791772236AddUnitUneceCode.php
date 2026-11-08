<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1791772236AddUnitUneceCode extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1791772236;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'unit', 'unece_code', 'VARCHAR(3)');
    }
}
