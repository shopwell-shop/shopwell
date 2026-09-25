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
class Migration1765205483AddTrackOffcanvasCartToAnalytics extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1765205483;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'sales_channel_analytics', 'track_offcanvas_cart')) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `sales_channel_analytics`
            ADD COLUMN `track_offcanvas_cart` TINYINT(1) NOT NULL DEFAULT 0
        ');
    }
}
