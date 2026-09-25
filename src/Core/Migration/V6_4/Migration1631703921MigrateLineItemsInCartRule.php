<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1631703921MigrateLineItemsInCartRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1631703921;
    }

    public function update(Connection $connection): void
    {
        // moved to V6_5/Migration1669291632MigrateLineItemsInCartRule.php
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
