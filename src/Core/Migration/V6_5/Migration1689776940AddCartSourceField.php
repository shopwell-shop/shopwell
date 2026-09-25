<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1689776940AddCartSourceField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1689776940;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            connection: $connection,
            table: 'order',
            column: 'source',
            type: 'VARCHAR(255)'
        );
    }
}
