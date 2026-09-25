<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1615359964AddTaxFreeFromAmountForCurrencyAndCountry extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1615359964;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'country', 'tax_free_from')) {
            $connection->executeStatement('
            ALTER TABLE `country` ADD COLUMN `tax_free_from` DOUBLE DEFAULT 0 AFTER `shipping_available`;
            ');
        }

        if (!TableHelper::columnExists($connection, 'currency', 'tax_free_from')) {
            $connection->executeStatement('
            ALTER TABLE `currency` ADD COLUMN `tax_free_from` DOUBLE DEFAULT 0 AFTER `total_rounding`;
            ');
        }
    }
}
