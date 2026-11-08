<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1536233530SalesChannelCategoryId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233530;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->removeNavigationFromSalesChannel($connection);
        $this->dropNavigation($connection);
    }

    private function removeNavigationFromSalesChannel(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `sales_channel` DROP FOREIGN KEY `fk.sales_channel.navigation_id`');

        $sql = <<<'SQL'
ALTER TABLE `sales_channel`
    DROP COLUMN `navigation_id`,
    DROP COLUMN `navigation_version_id`
SQL;

        $connection->executeStatement($sql);
    }

    private function dropNavigation(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE `navigation_translation`');
        $connection->executeStatement('DROP TABLE `navigation`');
    }
}
