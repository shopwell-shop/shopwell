<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1650872291CartAutoIncrement extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1650872291;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `cart` ADD `auto_increment` bigint NOT NULL AUTO_INCREMENT UNIQUE;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
