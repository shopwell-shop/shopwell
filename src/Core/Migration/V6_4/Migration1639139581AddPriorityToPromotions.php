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
class Migration1639139581AddPriorityToPromotions extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1639139581;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'promotion', 'priority')) {
            return;
        }

        $sql = <<<'SQL'
ALTER TABLE `promotion` ADD COLUMN `priority` INT(11) NOT NULL DEFAULT 1 AFTER `max_redemptions_per_customer`;
SQL;

        $connection->executeStatement($sql);
    }
}
