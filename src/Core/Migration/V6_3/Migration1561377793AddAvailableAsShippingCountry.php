<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1561377793AddAvailableAsShippingCountry extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1561377793;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `country` ADD `shipping_available` TinyInt(1) NOT NULL DEFAULT 1;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
