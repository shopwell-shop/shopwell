<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1788182786AddDefaultCurrenciesToSalesChannels extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1788182786;
    }

    public function update(Connection $connection): void
    {
        // A sales channel's default currency must be one of its available currencies. Backfill the missing
        // mappings on existing installations; INSERT IGNORE preserves mappings that are already present.
        $connection->executeStatement('INSERT IGNORE INTO `sales_channel_currency` (`sales_channel_id`, `currency_id`)
            SELECT `id`, `currency_id` FROM `sales_channel`');
    }
}
