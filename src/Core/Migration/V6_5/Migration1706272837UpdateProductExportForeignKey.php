<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1706272837UpdateProductExportForeignKey extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1706272837;
    }

    public function update(Connection $connection): void
    {
        $this->dropForeignKeyIfExists($connection, 'product_export', 'fk.product_export.sales_channel_domain_id');

        $connection->executeStatement(
            <<<'SQL'
            ALTER TABLE `product_export`
                ADD CONSTRAINT `fk.product_export.sales_channel_domain_id`
                    FOREIGN KEY (`sales_channel_domain_id`) REFERENCES `sales_channel_domain` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
SQL
        );
    }
}
