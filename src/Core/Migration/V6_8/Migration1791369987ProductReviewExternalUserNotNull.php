<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1791369987ProductReviewExternalUserNotNull extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1791369987;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'product_review', 'external_user')
            || TableHelper::getColumnOfTable($connection, 'product_review', 'external_user')->isNotNull
        ) {
            return;
        }

        $connection->executeStatement(
            'UPDATE `product_review`
             LEFT JOIN `customer` ON `customer`.`id` = `product_review`.`customer_id`
             SET `product_review`.`external_user` = COALESCE(`customer`.`first_name`, \'\')
             WHERE `product_review`.`external_user` IS NULL'
        );

        $this->executeDdlStatement(
            $connection,
            'ALTER TABLE `product_review` MODIFY COLUMN `external_user` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL'
        );
    }
}
