<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1691662140MigrateAvailableStock extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1691662140;
    }

    public function update(Connection $connection): void
    {
        do {
            $ids = $connection->fetchFirstColumn(
                <<<'SQL'
                    SELECT id
                    FROM product
                    WHERE stock != available_stock
                    LIMIT 1000
                SQL,
            );

            $connection->executeStatement(
                'UPDATE product SET stock = available_stock WHERE id IN (:ids)',
                ['ids' => $ids],
                ['ids' => ArrayParameterType::BINARY]
            );
        } while ($ids !== []);
    }
}
