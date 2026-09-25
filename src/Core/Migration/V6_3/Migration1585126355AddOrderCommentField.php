<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1585126355AddOrderCommentField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1585126355;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `order`
            ADD COLUMN `customer_comment` LONGTEXT COLLATE utf8mb4_unicode_ci NULL AFTER `campaign_code`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
