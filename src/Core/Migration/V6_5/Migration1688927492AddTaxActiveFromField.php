<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1688927492AddTaxActiveFromField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1688927492;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            connection: $connection,
            table: 'tax_rule',
            column: 'active_from',
            type: 'DATETIME(3)'
        );
    }
}
