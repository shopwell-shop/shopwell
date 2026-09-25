<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1600253602AddOrderRuleIds extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1600253602;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `order` ADD COLUMN `rule_ids` JSON NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
