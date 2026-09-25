<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Adapter\Storage\MySQLKeyValueStorage;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1702479623RemoveEsMultilingualFlag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1702479623;
    }

    public function update(Connection $connection): void
    {
        $storage = new MySQLKeyValueStorage($connection);

        $storage->remove('enable-multilingual-index');
    }
}
