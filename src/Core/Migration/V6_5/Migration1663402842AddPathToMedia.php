<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1663402842AddPathToMedia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1663402842;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            connection: $connection,
            table: 'media',
            column: 'path',
            type: 'VARCHAR(2048)'
        );

        $this->addColumn(
            connection: $connection,
            table: 'media_thumbnail',
            column: 'path',
            type: 'VARCHAR(2048)'
        );

        $this->registerIndexer($connection, 'media.path.post_update');
    }
}
