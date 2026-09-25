<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1574063550AddCurrencyToProductExport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1574063550;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE product_export ADD COLUMN currency_id BINARY(16) NOT NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
