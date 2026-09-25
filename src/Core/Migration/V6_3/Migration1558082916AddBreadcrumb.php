<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1558082916AddBreadcrumb extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1558082916;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `category_translation` ADD `breadcrumb` json NULL AFTER `name`;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
