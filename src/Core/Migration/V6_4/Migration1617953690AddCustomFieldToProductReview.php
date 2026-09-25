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
class Migration1617953690AddCustomFieldToProductReview extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1617953690;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'product_review', 'custom_fields')) {
            $connection->executeStatement(
                'ALTER TABLE `product_review`
                ADD COLUMN `custom_fields` JSON NULL AFTER `comment`,
                ADD CONSTRAINT `json.product_review.custom_fields` CHECK (JSON_VALID(`custom_fields`));'
            );
        }
    }
}
