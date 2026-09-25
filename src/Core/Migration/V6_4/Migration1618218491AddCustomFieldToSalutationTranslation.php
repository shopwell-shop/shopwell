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
class Migration1618218491AddCustomFieldToSalutationTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1618218491;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'salutation_translation', 'custom_fields')) {
            $connection->executeStatement(
                'ALTER TABLE `salutation_translation`
                ADD COLUMN `custom_fields` JSON NULL AFTER `letter_name`,
                ADD CONSTRAINT `json.salutation_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`));'
            );
        }
    }
}
