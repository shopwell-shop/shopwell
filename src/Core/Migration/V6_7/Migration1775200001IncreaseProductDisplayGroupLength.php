<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1775200001IncreaseProductDisplayGroupLength extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1775200001;
    }

    public function update(Connection $connection): void
    {
        $this->widenDisplayGroupColumnForSha256IfNeeded($connection);
    }

    private function widenDisplayGroupColumnForSha256IfNeeded(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, ProductDefinition::ENTITY_NAME, 'display_group')) {
            return;
        }

        $column = TableHelper::getColumnOfTable($connection, ProductDefinition::ENTITY_NAME, 'display_group');

        if ($column->type === 'string' && $column->length !== null && $column->length >= 64) {
            return;
        }

        $this->executeDdlStatement($connection, 'ALTER TABLE `product` MODIFY `display_group` VARCHAR(64) NULL');
    }
}
