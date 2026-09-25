<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1770903396AddRequirementsToServiceSourceConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770903396;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            UPDATE `app`
            SET `source_config` = JSON_SET(`source_config`, \'$.requirements\', JSON_ARRAY(\'service_consent\'))
            WHERE `self_managed` = 1
            AND `source_config` IS NOT NULL
            AND (
                JSON_EXTRACT(`source_config`, \'$.requirements\') IS NULL
                OR JSON_TYPE(JSON_EXTRACT(`source_config`, \'$.requirements\')) != \'ARRAY\'
            )
        ');
    }
}
