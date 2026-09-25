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
class Migration1646817331AddCmsClassColumnCmsPage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1646817331;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'cms_page', 'css_class')) {
            $connection->executeStatement('ALTER TABLE `cms_page` ADD `css_class` VARCHAR(255) NULL AFTER `locked`;');
        }
    }
}
