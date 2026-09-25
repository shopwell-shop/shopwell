<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1622782058AddDeleteAtIntoIntegrationAndAclRole extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1622782058;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'integration', 'deleted_at')) {
            $connection->executeStatement('ALTER TABLE `integration` ADD COLUMN `deleted_at` DATETIME(3) NULL');
        }

        if (!TableHelper::columnExists($connection, 'acl_role', 'deleted_at')) {
            $connection->executeStatement('ALTER TABLE `acl_role` ADD COLUMN `deleted_at` DATETIME(3) NULL');
        }
    }
}
