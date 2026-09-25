<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1735807464AddCustomFieldStoreApiAware extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1735807464;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            connection: $connection,
            table: 'custom_field',
            column: 'store_api_aware',
            type: 'TINYINT(1)',
            nullable: false,
            default: '1',
        );
    }
}
