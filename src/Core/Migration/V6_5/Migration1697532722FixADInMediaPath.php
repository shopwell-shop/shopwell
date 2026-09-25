<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1697532722FixADInMediaPath extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1697532722;
    }

    public function update(Connection $connection): void
    {
        // replace /ad/ with /g0/ in media.path and media_thumbnail.path
        $connection->executeStatement('UPDATE media SET path = REPLACE(path, \'/ad/\', \'/g0/\') WHERE path LIKE \'%/ad/%\'');
        $connection->executeStatement('UPDATE media_thumbnail SET path = REPLACE(path, \'/ad/\', \'/g0/\') WHERE path LIKE \'%/ad/%\'');
    }
}
