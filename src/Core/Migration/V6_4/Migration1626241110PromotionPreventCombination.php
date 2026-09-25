<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1626241110PromotionPreventCombination extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1626241110;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `promotion` ADD COLUMN `prevent_combination` TINYINT(1) NOT NULL DEFAULT 0 AFTER `customer_restriction`;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
