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
class Migration1618569201AddCustomFieldToDocumentBaseConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1618569201;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'document_base_config', 'custom_fields')) {
            $connection->executeStatement(
                'ALTER TABLE `document_base_config`
                ADD COLUMN `custom_fields` JSON NULL AFTER `config`,
                ADD CONSTRAINT `json.document_base_config.custom_fields` CHECK (JSON_VALID(`custom_fields`));'
            );
        }
    }
}
