<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1602062376AddUniqueConstraintForEmailAndBoundSalesChannelIdIntoCustomerTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1602062376;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `customer` ADD UNIQUE `uniq.customer.email_bound_sales_channel_id`(`email`, `bound_sales_channel_id`);');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
