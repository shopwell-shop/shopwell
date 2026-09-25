<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1736866790AddDocumentA11yMediaFileIdForDocumentTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1736866790;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'document',
            'document_a11y_media_file_id',
            'BINARY(16)',
        );

        if (TableHelper::foreignKeyExistsByColumns($connection, 'document', ['document_a11y_media_file_id'], 'media', ['id'])) {
            return;
        }

        $connection->executeStatement(<<<'SQL'
            ALTER TABLE `document`
            ADD CONSTRAINT `fk.document.document_a11y_media_file_id` FOREIGN KEY (`document_a11y_media_file_id`)
            REFERENCES `media` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
        SQL);
    }
}
