<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1612970642AddHomeSettingsToSalesChannel extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1612970642;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
ALTER TABLE `sales_channel_translation`
    ADD COLUMN `home_enabled`           TINYINT        NOT NULL DEFAULT 1  AFTER `name`,
    ADD COLUMN `home_name`              VARCHAR(255)   NULL                AFTER `name`,
    ADD COLUMN `home_meta_title`        VARCHAR(255)   NULL                AFTER `name`,
    ADD COLUMN `home_meta_description`  VARCHAR(255)   NULL                AFTER `name`,
    ADD COLUMN `home_keywords`          VARCHAR(255)   NULL                AFTER `name`
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
