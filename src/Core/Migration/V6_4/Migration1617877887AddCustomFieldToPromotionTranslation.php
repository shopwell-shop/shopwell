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
class Migration1617877887AddCustomFieldToPromotionTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1617877887;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'promotion_translation', 'custom_fields')) {
            $connection->executeStatement(
                'ALTER TABLE `promotion_translation`
                ADD COLUMN `custom_fields` JSON NULL AFTER `language_id`,
                ADD CONSTRAINT `json.promotion_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`));'
            );
        }
    }
}
