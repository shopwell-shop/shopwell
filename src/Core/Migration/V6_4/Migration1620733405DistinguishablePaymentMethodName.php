<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1620733405DistinguishablePaymentMethodName extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1620733405;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `payment_method_translation`
            ADD COLUMN `distinguishable_name` VARCHAR(255) NULL AFTER `name`
        ');

        $this->registerIndexer($connection, 'payment_method.indexer');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
