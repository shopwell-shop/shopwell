<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
class Migration1675323588ChangeEnglishLocaleTranslationOfUsLocale extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1675323588;
    }

    public function update(Connection $connection): void
    {
        $usLocaleId = $connection->fetchOne(
            '
            SELECT locale.id
            FROM `locale`
            WHERE LOWER(locale.code) = LOWER(:iso)',
            ['iso' => 'en-us']
        );

        if (!$usLocaleId) {
            return;
        }

        $enLangId = $this->fetchLanguageId('en-GB', $connection);
        if ($enLangId) {
            $connection->executeStatement(
                'UPDATE locale_translation
                SET name = :newName
                WHERE locale_id = :locale_id AND language_id = :language_id
                AND name = :oldName',
                [
                    'locale_id' => $usLocaleId,
                    'language_id' => $enLangId,
                    'oldName' => 'English',
                    'newName' => 'English (US)',
                ]
            );
        }

        $zhCnLangId = $this->fetchLanguageId('zh-CN', $connection);
        if ($zhCnLangId) {
            $connection->executeStatement(
                'UPDATE locale_translation
            SET name = :newName
            WHERE locale_id = :locale_id AND language_id = :language_id
            AND name = :oldName',
                [
                    'locale_id' => $usLocaleId,
                    'language_id' => $zhCnLangId,
                    'oldName' => '英语',
                    'newName' => '英语（美国）',
                ]
            );
        }
    }

    private function fetchLanguageId(string $code, Connection $connection): ?string
    {
        $langId = $connection->fetchOne(
            'SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`translation_code_id` = `locale`.`id` WHERE `code` = :code LIMIT 1',
            ['code' => $code]
        );
        if ($langId === false) {
            return null;
        }

        return (string) $langId;
    }
}
