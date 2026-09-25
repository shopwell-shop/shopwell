<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1648031611AddOrderLineItemPromotionId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1648031611;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'order_line_item', 'promotion_id')) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `order_line_item` ADD `promotion_id` binary(16) NULL AFTER `product_version_id`');
        $connection->executeStatement('ALTER TABLE `order_line_item` ADD CONSTRAINT `fk.order_line_item.promotion_id` FOREIGN KEY (`promotion_id`) REFERENCES `promotion` (`id`) ON DELETE SET NULL ON UPDATE CASCADE');
        $connection->executeStatement(\sprintf('UPDATE IGNORE `order_line_item` SET `promotion_id` = UNHEX(JSON_UNQUOTE(JSON_EXTRACT(`payload`, \'$.promotionId\'))) WHERE type = \'%s\'', PromotionProcessor::LINE_ITEM_TYPE));
    }
}
