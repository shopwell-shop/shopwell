<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1595499689RemoveGoogleShoppingRelatedTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1595499689;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            DROP TABLE IF EXISTS google_shopping_ads_account;
            DROP TABLE IF EXISTS google_shopping_merchant_account;
            DROP TABLE IF EXISTS google_shopping_account;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
