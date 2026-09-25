<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1788531955MakeProductGuaranteeConfirmedInheritable extends MigrationStep
{
    private const UPDATE_LIMIT = 1000;

    public function getCreationTimestamp(): int
    {
        return 1788531955;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'product', 'guarantee_confirmed')) {
            return;
        }

        if (TableHelper::getColumnOfTable($connection, 'product', 'guarantee_confirmed')->isNotNull) {
            $this->executeDdlStatement(
                $connection,
                'ALTER TABLE `product` MODIFY COLUMN `guarantee_confirmed` TINYINT(1) NULL DEFAULT NULL'
            );
        }

        do {
            $affectedRows = (int) $connection->executeStatement(
                'UPDATE `product`
                 SET `guarantee_confirmed` = NULL
                 WHERE `parent_id` IS NOT NULL AND `guarantee_confirmed` = 0
                 LIMIT :limit',
                ['limit' => self::UPDATE_LIMIT],
                ['limit' => ParameterType::INTEGER]
            );
        } while ($affectedRows === self::UPDATE_LIMIT);

        $this->registerIndexer($connection, 'product.indexer');
    }
}
