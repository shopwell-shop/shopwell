<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1763125891AddProductTypeColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1763125891;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'product', 'type')) {
            $this->addColumn(
                $connection,
                'product',
                'type',
                'VARCHAR(32)',
                false,
                '\'physical\''
            );

            $this->executeDdlStatement($connection, 'CREATE INDEX `idx.product.type` ON `product` (`type`)');
        }

        if (!TableHelper::indexExists($connection, 'product', 'idx.product.type')) {
            $this->executeDdlStatement($connection, 'CREATE INDEX `idx.product.type` ON `product` (`type`)');
        }

        $batchSize = 5000;

        do {
            $affected = $connection->executeStatement(
                "UPDATE `product`
                 SET `product`.`type` = 'digital'
                 WHERE `type` <> 'digital' AND JSON_CONTAINS(states, '\"is-download\"')
                 ORDER BY `id`
                 LIMIT {$batchSize};"
            );
        } while ($affected > 0);
    }
}
