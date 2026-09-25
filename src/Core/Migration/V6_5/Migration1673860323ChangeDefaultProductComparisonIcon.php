<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1673860323ChangeDefaultProductComparisonIcon extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1673860323;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE `sales_channel_type` SET `icon_name` = "regular-rocket" WHERE `icon_name` = "default-object-rocket"');
    }
}
