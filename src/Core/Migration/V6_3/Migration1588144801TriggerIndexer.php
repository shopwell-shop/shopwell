<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1588144801TriggerIndexer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1588144801;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('TRUNCATE product_keyword_dictionary');
        $this->registerIndexer($connection, 'product.indexer');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
