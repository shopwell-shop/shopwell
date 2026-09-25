<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1788485938AddPromotionRedemptionIndexToOrderLineItem extends MigrationStep
{
    private const INDEX_NAME = 'idx.order_line_item.promotion_redemption';

    public function getCreationTimestamp(): int
    {
        return 1788485938;
    }

    public function update(Connection $connection): void
    {
        // The recount reads all four columns; this covering index avoids the row lookups.
        if (TableHelper::indexExists($connection, 'order_line_item', self::INDEX_NAME)) {
            return;
        }

        $connection->executeStatement(
            'CREATE INDEX `' . self::INDEX_NAME . '` ON `order_line_item` '
            . '(`promotion_id`, `version_id`, `order_id`, `order_version_id`)'
        );
    }
}
