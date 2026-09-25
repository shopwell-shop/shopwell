<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1563518181PromotionDiscount extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1563518181;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `promotion_discount` ADD `max_value` FLOAT DEFAULT NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
