<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1761739065IncreaseProductWeightPrecision extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1761739065;
    }

    public function update(Connection $connection): void
    {
        if (!$this->isProductWeightUsingDefaultPrecision($connection)) {
            return;
        }

        $this->executeDdlStatement($connection, 'ALTER TABLE `product` MODIFY `weight` DECIMAL(15,6) UNSIGNED NULL');
    }

    private function isProductWeightUsingDefaultPrecision(Connection $connection): bool
    {
        $columnTypeQuery = <<<'SQL'
            SELECT LOWER(COLUMN_TYPE)
            FROM information_schema.columns
            WHERE table_schema = :schema
              AND table_name = 'product'
              AND column_name = 'weight';
        SQL;

        $columnType = $connection->fetchOne($columnTypeQuery, ['schema' => $connection->getDatabase()]);

        return \is_string($columnType) && \str_contains($columnType, 'decimal(10,3)');
    }
}
