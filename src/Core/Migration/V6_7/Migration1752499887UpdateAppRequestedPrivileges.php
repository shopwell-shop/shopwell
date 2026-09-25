<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1752499887UpdateAppRequestedPrivileges extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1752499887;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'app', 'requested_privileges')) {
            // prevent new NULL entries
            $connection->executeStatement('
                ALTER TABLE `app`
                MODIFY COLUMN `requested_privileges` JSON DEFAULT (JSON_ARRAY())
            ');

            $connection->executeStatement('
                UPDATE `app`
                SET requested_privileges = JSON_ARRAY()
                WHERE requested_privileges IS NULL
            ');

            // all values are now guaranteed to be non-NULL, so we can change the column to NOT NULL.
            $connection->executeStatement('
                ALTER TABLE `app`
                MODIFY COLUMN `requested_privileges` JSON NOT NULL DEFAULT (JSON_ARRAY())
            ');
        }
    }
}
