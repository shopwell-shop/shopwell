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
class Migration1664512574AddConfigShowHideSectionBlock extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1664512574;
    }

    public function update(Connection $connection): void
    {
        $this->updateSchema($connection, 'cms_section');
        $this->updateSchema($connection, 'cms_block');
    }

    /**
     * @param non-empty-string $tableName
     */
    private function updateSchema(Connection $connection, string $tableName): void
    {
        if (!TableHelper::columnExists($connection, $tableName, 'visibility')) {
            $connection->executeStatement(\sprintf('ALTER TABLE `%s` ADD COLUMN `visibility` JSON NULL AFTER `background_media_mode`', $tableName));
        }
    }
}
