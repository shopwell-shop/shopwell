<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1602494495SetUsersAsAdmins extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1602494495;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE `user` SET `admin` = 1, `title` = `Admin`');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
