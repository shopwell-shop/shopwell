<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1794296173RepairOrderReturnReasonTranslations extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794296173;
    }

    public function update(Connection $connection): void
    {
        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $englishLanguageId = $connection->fetchOne(
            'SELECT `language`.`id` FROM `language`
             INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
             WHERE `locale`.`code` = :code
             ORDER BY `language`.`created_at`, `language`.`id`
             LIMIT 1',
            ['code' => 'en-GB']
        );

        if ($englishLanguageId === false || $englishLanguageId === $defaultLanguageId) {
            return;
        }

        // Older installations can have reasons only in the former English system language.
        // Fill the missing fallback, preserving every existing merchant translation.
        $connection->executeStatement(
            'INSERT INTO `order_return_reason_translation`
                (`order_return_reason_id`, `language_id`, `label`, `custom_fields`, `created_at`)
             SELECT `source`.`order_return_reason_id`, :defaultLanguageId, `source`.`label`, `source`.`custom_fields`, :createdAt
             FROM `order_return_reason_translation` AS `source`
             WHERE `source`.`language_id` = :englishLanguageId
               AND `source`.`label` IS NOT NULL AND TRIM(`source`.`label`) <> \'\'
               AND NOT EXISTS (
                   SELECT 1 FROM `order_return_reason_translation` AS `existing`
                   WHERE `existing`.`order_return_reason_id` = `source`.`order_return_reason_id`
                     AND `existing`.`language_id` = :defaultLanguageId
               )',
            [
                'defaultLanguageId' => $defaultLanguageId,
                'englishLanguageId' => $englishLanguageId,
                'createdAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }
}
