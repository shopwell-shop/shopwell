<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1772098113AddEnhancedConversionsToAnalytics extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1772098113;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'sales_channel_analytics', 'enhanced_conversions')) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `sales_channel_analytics`
            ADD COLUMN `enhanced_conversions` TINYINT(1) NOT NULL DEFAULT 0
        ');
    }
}
