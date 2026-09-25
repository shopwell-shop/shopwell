<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1680789830AddCustomFieldsAwareToCustomEntities extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1680789830;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            connection: $connection,
            table: 'custom_entity',
            column: 'custom_fields_aware',
            type: 'TINYINT(1)',
            nullable: false,
            default: '0'
        );

        $this->addColumn(
            connection: $connection,
            table: 'custom_entity',
            column: 'label_property',
            type: 'VARCHAR(255)'
        );
    }
}
