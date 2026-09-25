<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Promotion\PromotionDefinition;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1607415095PromotionRedemptionsNullable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1607415095;
    }

    public function update(Connection $connection): void
    {
        $sql = str_replace(
            ['#table#'],
            [PromotionDefinition::ENTITY_NAME],
            'ALTER TABLE `#table#`
                MODIFY COLUMN `max_redemptions_global`       INT NULL,
                MODIFY COLUMN `max_redemptions_per_customer` INT NULL;'
        );

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
