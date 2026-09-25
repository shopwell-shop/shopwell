<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1598280548NullableSeoUrlTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1598280548;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `seo_url_template` MODIFY `template` VARCHAR(750) NULL;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
