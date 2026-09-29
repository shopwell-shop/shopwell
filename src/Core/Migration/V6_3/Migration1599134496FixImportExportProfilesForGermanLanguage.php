<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\DataAbstractionLayer\Util\StatementHelper;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1599134496FixImportExportProfilesForGermanLanguage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1599134496;
    }

    public function update(Connection $connection): void
    {
        $zhCnLanguageId = $connection->fetchOne('
            SELECT lang.id
            FROM language lang
            INNER JOIN locale loc ON lang.locale_id = loc.id
            AND loc.code = \'zh-CN\';
        ');

        if (!$zhCnLanguageId) {
            return;
        }

        $englishLanguageId = $connection->fetchOne('
            SELECT lang.id
            FROM language lang
            INNER JOIN locale loc ON lang.locale_id = loc.id
            AND loc.code = \'en-GB\';
        ');

        $sql = <<<'SQL'
            SELECT *
            FROM import_export_profile_translation AS `translation`
            INNER JOIN import_export_profile AS `profile` ON translation.import_export_profile_id = profile.id
            WHERE profile.system_default = 1
            AND language_id = :languageId
SQL;

        $englishData = $connection->fetchAllAssociative($sql, [
            'languageId' => $englishLanguageId,
        ]);
        $zhCnData = $connection->fetchAllAssociative($sql, [
            'languageId' => $zhCnLanguageId,
        ]);
        $zhCnTranslations = $this->getZhCnTranslationData();

        $insertSql = <<<'SQL'
            INSERT INTO import_export_profile_translation (`import_export_profile_id`, `language_id`, `label`, `created_at`)
            VALUES (:import_export_profile_id, :language_id, :label, :created_at)
SQL;

        $stmt = $connection->prepare($insertSql);
        foreach ($englishData as $data) {
            if ($this->checkIfInZhCnData($data, $zhCnData)) {
                continue;
            }

            StatementHelper::executeStatement($stmt, [
                'import_export_profile_id' => $data['import_export_profile_id'],
                'language_id' => $zhCnLanguageId,
                'label' => $zhCnTranslations[$data['label']],
                'created_at' => $data['created_at'],
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    /**
     * @return array<string, string>
     */
    private function getZhCnTranslationData(): array
    {
        return [
            'Default category' => '标准配置 - 分类',
            'Default media' => '标准配置 - 媒体',
            'Default variant configuration settings' => '标准配置 - 变体配置',
            'Default newsletter recipient' => '标准配置 - 邮件通讯收件人',
            'Default properties' => '标准配置 - 属性',
            'Default product' => '标准配置 - 商品',
        ];
    }

    /**
     * @param array<string, mixed> $englishRow
     * @param array<array<string, mixed>> $zhCnData
     */
    private function checkIfInZhCnData(array $englishRow, array $zhCnData): bool
    {
        $zhCnProfileIds = array_column($zhCnData, 'import_export_profile_id');

        return \in_array($englishRow['import_export_profile_id'], $zhCnProfileIds, true);
    }
}
