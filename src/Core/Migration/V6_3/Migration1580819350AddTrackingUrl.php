<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1580819350AddTrackingUrl extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1580819350;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `shipping_method_translation` ADD `tracking_url` MEDIUMTEXT NULL DEFAULT NULL AFTER `description`;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
