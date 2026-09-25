<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1738661307AddMediaIndices extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1738661307;
    }

    public function update(Connection $connection): void
    {
        $this->dropIndexIfExists($connection, 'media', 'idx.media.file_extension');
        $connection->executeStatement(
            <<<'SQL'
            CREATE INDEX `idx.media.file_extension`
                ON `media` (`file_extension`);
            SQL
        );

        $this->dropIndexIfExists($connection, 'media', 'idx.media.file_name');
        $connection->executeStatement(
            <<<'SQL'
            CREATE INDEX `idx.media.file_name`
                ON `media` (`file_name`(768));
            SQL
        );

        if (!TableHelper::columnExists($connection, 'media', 'file_hash')) {
            $connection->executeStatement(
                <<<'SQL'
                ALTER TABLE `media` ADD COLUMN `file_hash` VARCHAR(32)
                    GENERATED ALWAYS AS (
                        JSON_UNQUOTE(JSON_EXTRACT(meta_data, '$.hash'))
                    ) STORED;
                SQL
            );
        }

        $this->dropIndexIfExists($connection, 'media', 'idx.media.file_hash');
        $connection->executeStatement(
            <<<'SQL'
            CREATE INDEX `idx.media.file_hash`
                ON `media` (`file_hash`);
            SQL
        );
    }
}
