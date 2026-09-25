<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1764064756AddCustomFieldSearchable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1764064756;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            connection: $connection,
            table: 'custom_field',
            column: 'include_in_search',
            type: 'TINYINT(1)',
            nullable: false,
            default: '0',
        );
    }
}
