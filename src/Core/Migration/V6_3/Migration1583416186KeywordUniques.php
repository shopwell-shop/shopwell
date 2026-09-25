<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1583416186KeywordUniques extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1583416186;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('TRUNCATE product_keyword_dictionary');

        $connection->executeStatement('ALTER TABLE `product_keyword_dictionary` ADD UNIQUE `uniq.language_id_keyword` (`language_id`, `keyword`);');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
