<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1582011195FixCountryStateGermanTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1582011195;
    }

    public function update(Connection $connection): void
    {
        $default = [
            'DE-BW' => 'Baden-Württemberg',
            'DE-BY' => 'Bavaria',
            'DE-BE' => 'Berlin',
            'DE-BB' => 'Brandenburg',
            'DE-HB' => 'Bremen',
            'DE-HH' => 'Hamburg',
            'DE-HE' => 'Hesse',
            'DE-NI' => 'Lower Saxony',
            'DE-MV' => 'Mecklenburg-Western Pomerania',
            'DE-NW' => 'North Rhine-Westphalia',
            'DE-RP' => 'Rhineland-Palatinate',
            'DE-SL' => 'Saarland',
            'DE-SN' => 'Saxony',
            'DE-ST' => 'Saxony-Anhalt',
            'DE-SH' => 'Schleswig-Holstein',
            'DE-TH' => 'Thuringia',
        ];

        $zhCnTranslations = [
            'DE-BW' => '巴登-符腾堡',
            'DE-BY' => '巴伐利亚',
            'DE-BE' => '柏林',
            'DE-BB' => '勃兰登堡',
            'DE-HB' => '不来梅',
            'DE-HH' => '汉堡',
            'DE-HE' => '黑森',
            'DE-NI' => '下萨克森',
            'DE-MV' => '梅克伦堡-前波美拉尼亚',
            'DE-NW' => '北莱茵-威斯特法伦',
            'DE-RP' => '莱茵兰-普法尔茨',
            'DE-SL' => '萨尔',
            'DE-SN' => '萨克森',
            'DE-ST' => '萨克森-安哈尔特',
            'DE-SH' => '石勒苏益格-荷尔斯泰因',
            'DE-TH' => '图林根',
        ];

        $zhCnLanguageId = $connection->createQueryBuilder()
            ->select('lang.id')
            ->from('language', 'lang')
            ->innerJoin('lang', 'locale', 'loc', 'lang.translation_code_id = loc.id')
            ->where('loc.code = :zhCnLocale')
            ->setParameter('zhCnLocale', 'zh-CN')
            ->executeQuery()
            ->fetchOne();

        if (!$zhCnLanguageId) {
            return;
        }

        $translations = $connection->createQueryBuilder()
            ->select('state.short_code, state.id, state_translation.name')
            ->from('country_state', 'state')
            ->innerJoin(
                'state',
                'country_state_translation',
                'state_translation',
                'state.id = state_translation.country_state_id AND state_translation.language_id = :zhCnLangId'
            )->where('state.short_code IN (:shortCodes)')
            ->setParameter('zhCnLangId', $zhCnLanguageId)
            ->setParameter('shortCodes', array_keys($default), ArrayParameterType::STRING)
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($translations as $translation) {
            $shortCode = $translation['short_code'];

            if ($translation['name'] !== $default[$shortCode]) {
                continue;
            }

            $connection->update(
                'country_state_translation',
                ['name' => $zhCnTranslations[$shortCode]],
                [
                    'country_state_id' => $translation['id'],
                    'language_id' => $zhCnLanguageId,
                ]
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
