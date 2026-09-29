<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1589357321AddCountries extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1589357321;
    }

    public function update(Connection $connection): void
    {
        $zhCnLanguageId = $this->getLanguageId($connection, 'zh-CN');
        $languageZhCn = null;
        if ($zhCnLanguageId && $zhCnLanguageId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
            $languageZhCn = static fn (string $countryId, string $name) => [
                'language_id' => $zhCnLanguageId,
                'name' => $name,
                'country_id' => $countryId,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];
        }

        $enLanguageId = $this->getLanguageId($connection, 'en-GB');
        $languageEN = null;
        if ($enLanguageId && $enLanguageId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
            $languageEN = static fn (string $countryId, string $name) => [
                'language_id' => $enLanguageId,
                'name' => $name,
                'country_id' => $countryId,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];
        }

        $default = static fn (string $countryId, string $name) => [
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'name' => $name,
            'country_id' => $countryId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        foreach ($this->createNewCountries() as $country) {
            $id = Uuid::randomBytes();
            $exists = $connection->fetchOne('SELECT 1 FROM country WHERE iso = :iso3', ['iso3' => $country['iso3']]);
            if ($exists !== false) {
                continue;
            }

            $connection->insert('country', ['id' => $id, 'iso' => $country['iso'], 'position' => 10, 'iso3' => $country['iso3'], 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
            $defaultTranslations = $country['en'];
            if ($zhCnLanguageId === Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
                $defaultTranslations = $country['zh'];
            }
            $connection->insert('country_translation', $default($id, $defaultTranslations));

            if ($languageZhCn !== null) {
                $connection->insert('country_translation', $languageZhCn($id, $country['zh']));
            }
            if ($languageEN !== null) {
                $connection->insert('country_translation', $languageEN($id, $country['en']));
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function getLanguageId(Connection $connection, string $code): string
    {
        $sql = <<<'SQL'
            SELECT id
            FROM `language`
            WHERE translation_code_id = (
               SELECT id
               FROM locale
               WHERE locale.code = :code
            )
            ORDER BY created_at ASC
SQL;

        return (string) $connection->executeQuery($sql, ['code' => $code])->fetchOne();
    }

    /**
     * @return list<array{iso: string, iso3: string, zh: string, en: string}>
     */
    private function createNewCountries(): array
    {
        return [
            [
                'iso' => 'BG',
                'iso3' => 'BGR',
                'zh' => '保加利亚',
                'en' => 'Bulgaria',
            ], [
                'iso' => 'EE',
                'iso3' => 'EST',
                'zh' => '爱沙尼亚',
                'en' => 'Estonia',
            ], [
                'iso' => 'HR',
                'iso3' => 'HRV',
                'zh' => '克罗地亚',
                'en' => 'Croatia',
            ], [
                'iso' => 'LV',
                'iso3' => 'LVA',
                'zh' => '拉脱维亚',
                'en' => 'Latvia',
            ], [
                'iso' => 'LT',
                'iso3' => 'LTU',
                'zh' => '立陶宛',
                'en' => 'Lithuania',
            ], [
                'iso' => 'MT',
                'iso3' => 'MLT',
                'zh' => '马耳他',
                'en' => 'Malta',
            ], [
                'iso' => 'SI',
                'iso3' => 'SVN',
                'zh' => '斯洛文尼亚',
                'en' => 'Slovenia',
            ], [
                'iso' => 'CY',
                'iso3' => 'CYP',
                'zh' => '塞浦路斯',
                'en' => 'Cyprus',
            ], [
                'iso' => 'AF',
                'iso3' => 'AFG',
                'zh' => '阿富汗',
                'en' => 'Afghanistan',
            ], [
                'iso' => 'AX',
                'iso3' => 'ALA',
                'zh' => '奥兰群岛',
                'en' => 'Åland Islands',
            ], [
                'iso' => 'AL',
                'iso3' => 'ALB',
                'zh' => '阿尔巴尼亚',
                'en' => 'Albania',
            ], [
                'iso' => 'DZ',
                'iso3' => 'DZA',
                'zh' => '阿尔及利亚',
                'en' => 'Algeria',
            ], [
                'iso' => 'AS',
                'iso3' => 'ASM',
                'zh' => '美属萨摩亚',
                'en' => 'American Samoa',
            ], [
                'iso' => 'AD',
                'iso3' => 'AND',
                'zh' => '安道尔',
                'en' => 'Andorra',
            ], [
                'iso' => 'AO',
                'iso3' => 'AGO',
                'zh' => '安哥拉',
                'en' => 'Angola',
            ], [
                'iso' => 'AI',
                'iso3' => 'AIA',
                'zh' => '安圭拉',
                'en' => 'Anguilla',
            ], [
                'iso' => 'AQ',
                'iso3' => 'ATA',
                'zh' => '南极洲',
                'en' => 'Antarctica',
            ], [
                'iso' => 'AG',
                'iso3' => 'ATG',
                'zh' => '安提瓜和巴布达',
                'en' => 'Antigua and Barbuda',
            ], [
                'iso' => 'AR',
                'iso3' => 'ARG',
                'zh' => '阿根廷',
                'en' => 'Argentina',
            ], [
                'iso' => 'AM',
                'iso3' => 'ARM',
                'zh' => '亚美尼亚',
                'en' => 'Armenia',
            ], [
                'iso' => 'AW',
                'iso3' => 'ABW',
                'zh' => '阿鲁巴',
                'en' => 'Aruba',
            ], [
                'iso' => 'AZ',
                'iso3' => 'AZE',
                'zh' => '阿塞拜疆',
                'en' => 'Azerbaijan',
            ], [
                'iso' => 'BS',
                'iso3' => 'BHS',
                'zh' => '巴哈马',
                'en' => 'Bahamas',
            ], [
                'iso' => 'BH',
                'iso3' => 'BHR',
                'zh' => '巴林',
                'en' => 'Bahrain',
            ], [
                'iso' => 'BD',
                'iso3' => 'BGD',
                'zh' => '孟加拉国',
                'en' => 'Bangladesh',
            ], [
                'iso' => 'BB',
                'iso3' => 'BRB',
                'zh' => '巴巴多斯',
                'en' => 'Barbados',
            ], [
                'iso' => 'BY',
                'iso3' => 'BLR',
                'zh' => '白俄罗斯',
                'en' => 'Belarus',
            ], [
                'iso' => 'BZ',
                'iso3' => 'BLZ',
                'zh' => '伯利兹',
                'en' => 'Belize',
            ], [
                'iso' => 'BJ',
                'iso3' => 'BEN',
                'zh' => '贝宁',
                'en' => 'Benin',
            ], [
                'iso' => 'BM',
                'iso3' => 'BMU',
                'zh' => '百慕大',
                'en' => 'Bermuda',
            ], [
                'iso' => 'BT',
                'iso3' => 'BTN',
                'zh' => '不丹',
                'en' => 'Bhutan',
            ], [
                'iso' => 'BO',
                'iso3' => 'BOL',
                'zh' => '玻利维亚',
                'en' => 'Bolivia (Plurinational State of)',
            ], [
                'iso' => 'BQ',
                'iso3' => 'BES',
                'zh' => '博奈尔、圣尤斯特歇斯和萨巴',
                'en' => 'Bonaire, Sint Eustatius and Saba',
            ], [
                'iso' => 'BA',
                'iso3' => 'BIH',
                'zh' => '波斯尼亚和黑塞哥维那',
                'en' => 'Bosnia and Herzegovina',
            ], [
                'iso' => 'BW',
                'iso3' => 'BWA',
                'zh' => '博茨瓦纳',
                'en' => 'Botswana',
            ], [
                'iso' => 'BV',
                'iso3' => 'BVT',
                'zh' => '布韦岛',
                'en' => 'Bouvet Island',
            ], [
                'iso' => 'IO',
                'iso3' => 'IOT',
                'zh' => '英属印度洋领地',
                'en' => 'British Indian Ocean Territory',
            ], [
                'iso' => 'UM',
                'iso3' => 'UMI',
                'zh' => '美国本土外小岛屿',
                'en' => 'United States Minor Outlying Islands',
            ], [
                'iso' => 'VG',
                'iso3' => 'VGB',
                'zh' => '英属维尔京群岛',
                'en' => 'Virgin Islands (British)',
            ], [
                'iso' => 'VI',
                'iso3' => 'VIR',
                'zh' => '美属维尔京群岛',
                'en' => 'Virgin Islands (U.S.)',
            ], [
                'iso' => 'BN',
                'iso3' => 'BRN',
                'zh' => '文莱',
                'en' => 'Brunei Darussalam',
            ], [
                'iso' => 'BF',
                'iso3' => 'BFA',
                'zh' => '布基纳法索',
                'en' => 'Burkina Faso',
            ], [
                'iso' => 'BI',
                'iso3' => 'BDI',
                'zh' => '布隆迪',
                'en' => 'Burundi',
            ], [
                'iso' => 'KH',
                'iso3' => 'KHM',
                'zh' => '柬埔寨',
                'en' => 'Cambodia',
            ], [
                'iso' => 'CM',
                'iso3' => 'CMR',
                'zh' => '喀麦隆',
                'en' => 'Cameroon',
            ], [
                'iso' => 'CV',
                'iso3' => 'CPV',
                'zh' => '佛得角',
                'en' => 'Cabo Verde',
            ], [
                'iso' => 'KY',
                'iso3' => 'CYM',
                'zh' => '开曼群岛',
                'en' => 'Cayman Islands',
            ], [
                'iso' => 'CF',
                'iso3' => 'CAF',
                'zh' => '中非共和国',
                'en' => 'Central African Republic',
            ], [
                'iso' => 'TD',
                'iso3' => 'TCD',
                'zh' => '乍得',
                'en' => 'Chad',
            ], [
                'iso' => 'CL',
                'iso3' => 'CHL',
                'zh' => '智利',
                'en' => 'Chile',
            ], [
                'iso' => 'CN',
                'iso3' => 'CHN',
                'zh' => '中国',
                'en' => 'China',
            ], [
                'iso' => 'CX',
                'iso3' => 'CXR',
                'zh' => '圣诞岛',
                'en' => 'Christmas Island',
            ], [
                'iso' => 'CC',
                'iso3' => 'CCK',
                'zh' => '科科斯（基林）群岛',
                'en' => 'Cocos (Keeling) Islands',
            ], [
                'iso' => 'CO',
                'iso3' => 'COL',
                'zh' => '哥伦比亚',
                'en' => 'Colombia',
            ], [
                'iso' => 'KM',
                'iso3' => 'COM',
                'zh' => '科摩罗',
                'en' => 'Comoros',
            ], [
                'iso' => 'CG',
                'iso3' => 'COG',
                'zh' => '刚果（布）',
                'en' => 'Congo',
            ], [
                'iso' => 'CD',
                'iso3' => 'COD',
                'zh' => '刚果（金）',
                'en' => 'Congo (Democratic Republic of the)',
            ], [
                'iso' => 'CK',
                'iso3' => 'COK',
                'zh' => '库克群岛',
                'en' => 'Cook Islands',
            ], [
                'iso' => 'CR',
                'iso3' => 'CRI',
                'zh' => '哥斯达黎加',
                'en' => 'Costa Rica',
            ], [
                'iso' => 'CU',
                'iso3' => 'CUB',
                'zh' => '古巴',
                'en' => 'Cuba',
            ], [
                'iso' => 'CW',
                'iso3' => 'CUW',
                'zh' => '库拉索',
                'en' => 'Curaçao',
            ], [
                'iso' => 'DJ',
                'iso3' => 'DJI',
                'zh' => '吉布提',
                'en' => 'Djibouti',
            ], [
                'iso' => 'DM',
                'iso3' => 'DMA',
                'zh' => '多米尼克',
                'en' => 'Dominica',
            ], [
                'iso' => 'DO',
                'iso3' => 'DOM',
                'zh' => '多米尼加共和国',
                'en' => 'Dominican Republic',
            ], [
                'iso' => 'EC',
                'iso3' => 'ECU',
                'zh' => '厄瓜多尔',
                'en' => 'Ecuador',
            ], [
                'iso' => 'EG',
                'iso3' => 'EGY',
                'zh' => '埃及',
                'en' => 'Egypt',
            ], [
                'iso' => 'SV',
                'iso3' => 'SLV',
                'zh' => '萨尔瓦多',
                'en' => 'El Salvador',
            ], [
                'iso' => 'GQ',
                'iso3' => 'GNQ',
                'zh' => '赤道几内亚',
                'en' => 'Equatorial Guinea',
            ], [
                'iso' => 'ER',
                'iso3' => 'ERI',
                'zh' => '厄立特里亚',
                'en' => 'Eritrea',
            ], [
                'iso' => 'ET',
                'iso3' => 'ETH',
                'zh' => '埃塞俄比亚',
                'en' => 'Ethiopia',
            ], [
                'iso' => 'FK',
                'iso3' => 'FLK',
                'zh' => '马尔维纳斯群岛（福克兰群岛）',
                'en' => 'Falkland Islands (Malvinas)',
            ], [
                'iso' => 'FO',
                'iso3' => 'FRO',
                'zh' => '法罗群岛',
                'en' => 'Faroe Islands',
            ], [
                'iso' => 'FJ',
                'iso3' => 'FJI',
                'zh' => '斐济',
                'en' => 'Fiji',
            ], [
                'iso' => 'GF',
                'iso3' => 'GUF',
                'zh' => '法属圭亚那',
                'en' => 'French Guiana',
            ], [
                'iso' => 'PF',
                'iso3' => 'PYF',
                'zh' => '法属波利尼西亚',
                'en' => 'French Polynesia',
            ], [
                'iso' => 'TF',
                'iso3' => 'ATF',
                'zh' => '法属南部领地',
                'en' => 'French Southern Territories',
            ], [
                'iso' => 'GA',
                'iso3' => 'GAB',
                'zh' => '加蓬',
                'en' => 'Gabon',
            ], [
                'iso' => 'GM',
                'iso3' => 'GMB',
                'zh' => '冈比亚',
                'en' => 'Gambia',
            ], [
                'iso' => 'GE',
                'iso3' => 'GEO',
                'zh' => '格鲁吉亚',
                'en' => 'Georgia',
            ], [
                'iso' => 'GH',
                'iso3' => 'GHA',
                'zh' => '加纳',
                'en' => 'Ghana',
            ], [
                'iso' => 'GI',
                'iso3' => 'GIB',
                'zh' => '直布罗陀',
                'en' => 'Gibraltar',
            ], [
                'iso' => 'GL',
                'iso3' => 'GRL',
                'zh' => '格陵兰',
                'en' => 'Greenland',
            ], [
                'iso' => 'GD',
                'iso3' => 'GRD',
                'zh' => '格林纳达',
                'en' => 'Grenada',
            ], [
                'iso' => 'GP',
                'iso3' => 'GLP',
                'zh' => '瓜德罗普',
                'en' => 'Guadeloupe',
            ], [
                'iso' => 'GU',
                'iso3' => 'GUM',
                'zh' => '关岛',
                'en' => 'Guam',
            ], [
                'iso' => 'GT',
                'iso3' => 'GTM',
                'zh' => '危地马拉',
                'en' => 'Guatemala',
            ], [
                'iso' => 'GG',
                'iso3' => 'GGY',
                'zh' => '根西',
                'en' => 'Guernsey',
            ], [
                'iso' => 'GN',
                'iso3' => 'GIN',
                'zh' => '几内亚',
                'en' => 'Guinea',
            ], [
                'iso' => 'GW',
                'iso3' => 'GNB',
                'zh' => '几内亚比绍',
                'en' => 'Guinea-Bissau',
            ], [
                'iso' => 'GY',
                'iso3' => 'GUY',
                'zh' => '圭亚那',
                'en' => 'Guyana',
            ], [
                'iso' => 'HT',
                'iso3' => 'HTI',
                'zh' => '海地',
                'en' => 'Haiti',
            ], [
                'iso' => 'HM',
                'iso3' => 'HMD',
                'zh' => '赫德岛和麦克唐纳群岛',
                'en' => 'Heard Island and McDonald Islands',
            ], [
                'iso' => 'VA',
                'iso3' => 'VAT',
                'zh' => '梵蒂冈',
                'en' => 'Holy See',
            ], [
                'iso' => 'HN',
                'iso3' => 'HND',
                'zh' => '洪都拉斯',
                'en' => 'Honduras',
            ], [
                'iso' => 'HK',
                'iso3' => 'HKG',
                'zh' => '中国香港',
                'en' => 'Hong Kong',
            ], [
                'iso' => 'IN',
                'iso3' => 'IND',
                'zh' => '印度',
                'en' => 'India',
            ], [
                'iso' => 'ID',
                'iso3' => 'IDN',
                'zh' => '印度尼西亚',
                'en' => 'Indonesia',
            ], [
                'iso' => 'CI',
                'iso3' => 'CIV',
                'zh' => '科特迪瓦',
                'en' => 'Côte d\'Ivoire',
            ], [
                'iso' => 'IR',
                'iso3' => 'IRN',
                'zh' => '伊朗',
                'en' => 'Iran (Islamic Republic of)',
            ], [
                'iso' => 'IQ',
                'iso3' => 'IRQ',
                'zh' => '伊拉克',
                'en' => 'Iraq',
            ], [
                'iso' => 'IM',
                'iso3' => 'IMN',
                'zh' => '马恩岛',
                'en' => 'Isle of Man',
            ], [
                'iso' => 'JM',
                'iso3' => 'JAM',
                'zh' => '牙买加',
                'en' => 'Jamaica',
            ], [
                'iso' => 'JE',
                'iso3' => 'JEY',
                'zh' => '泽西',
                'en' => 'Jersey',
            ], [
                'iso' => 'JO',
                'iso3' => 'JOR',
                'zh' => '约旦',
                'en' => 'Jordan',
            ], [
                'iso' => 'KZ',
                'iso3' => 'KAZ',
                'zh' => '哈萨克斯坦',
                'en' => 'Kazakhstan',
            ], [
                'iso' => 'KE',
                'iso3' => 'KEN',
                'zh' => '肯尼亚',
                'en' => 'Kenya',
            ], [
                'iso' => 'KI',
                'iso3' => 'KIR',
                'zh' => '基里巴斯',
                'en' => 'Kiribati',
            ], [
                'iso' => 'KW',
                'iso3' => 'KWT',
                'zh' => '科威特',
                'en' => 'Kuwait',
            ], [
                'iso' => 'KG',
                'iso3' => 'KGZ',
                'zh' => '吉尔吉斯斯坦',
                'en' => 'Kyrgyzstan',
            ], [
                'iso' => 'LA',
                'iso3' => 'LAO',
                'zh' => '老挝',
                'en' => 'Lao People\'s Democratic Republic',
            ], [
                'iso' => 'LB',
                'iso3' => 'LBN',
                'zh' => '黎巴嫩',
                'en' => 'Lebanon',
            ], [
                'iso' => 'LS',
                'iso3' => 'LSO',
                'zh' => '莱索托',
                'en' => 'Lesotho',
            ], [
                'iso' => 'LR',
                'iso3' => 'LBR',
                'zh' => '利比里亚',
                'en' => 'Liberia',
            ], [
                'iso' => 'LY',
                'iso3' => 'LBY',
                'zh' => '利比亚',
                'en' => 'Libya',
            ], [
                'iso' => 'MO',
                'iso3' => 'MAC',
                'zh' => '中国澳门',
                'en' => 'Macao',
            ], [
                'iso' => 'MK',
                'iso3' => 'MKD',
                'zh' => '北马其顿',
                'en' => 'Macedonia (the former Yugoslav Republic of)',
            ], [
                'iso' => 'MG',
                'iso3' => 'MDG',
                'zh' => '马达加斯加',
                'en' => 'Madagascar',
            ], [
                'iso' => 'MW',
                'iso3' => 'MWI',
                'zh' => '马拉维',
                'en' => 'Malawi',
            ], [
                'iso' => 'MY',
                'iso3' => 'MYS',
                'zh' => '马来西亚',
                'en' => 'Malaysia',
            ], [
                'iso' => 'MV',
                'iso3' => 'MDV',
                'zh' => '马尔代夫',
                'en' => 'Maldives',
            ], [
                'iso' => 'ML',
                'iso3' => 'MLI',
                'zh' => '马里',
                'en' => 'Mali',
            ], [
                'iso' => 'MH',
                'iso3' => 'MHL',
                'zh' => '马绍尔群岛',
                'en' => 'Marshall Islands',
            ], [
                'iso' => 'MQ',
                'iso3' => 'MTQ',
                'zh' => '马提尼克',
                'en' => 'Martinique',
            ], [
                'iso' => 'MR',
                'iso3' => 'MRT',
                'zh' => '毛里塔尼亚',
                'en' => 'Mauritania',
            ], [
                'iso' => 'MU',
                'iso3' => 'MUS',
                'zh' => '毛里求斯',
                'en' => 'Mauritius',
            ], [
                'iso' => 'YT',
                'iso3' => 'MYT',
                'zh' => '马约特',
                'en' => 'Mayotte',
            ], [
                'iso' => 'MX',
                'iso3' => 'MEX',
                'zh' => '墨西哥',
                'en' => 'Mexico',
            ], [
                'iso' => 'FM',
                'iso3' => 'FSM',
                'zh' => '密克罗尼西亚',
                'en' => 'Micronesia (Federated States of)',
            ], [
                'iso' => 'MD',
                'iso3' => 'MDA',
                'zh' => '摩尔多瓦',
                'en' => 'Moldova (Republic of)',
            ], [
                'iso' => 'MC',
                'iso3' => 'MCO',
                'zh' => '摩纳哥',
                'en' => 'Monaco',
            ], [
                'iso' => 'MN',
                'iso3' => 'MNG',
                'zh' => '蒙古',
                'en' => 'Mongolia',
            ], [
                'iso' => 'ME',
                'iso3' => 'MNE',
                'zh' => '黑山',
                'en' => 'Montenegro',
            ], [
                'iso' => 'MS',
                'iso3' => 'MSR',
                'zh' => '蒙特塞拉特',
                'en' => 'Montserrat',
            ], [
                'iso' => 'MA',
                'iso3' => 'MAR',
                'zh' => '摩洛哥',
                'en' => 'Morocco',
            ], [
                'iso' => 'MZ',
                'iso3' => 'MOZ',
                'zh' => '莫桑比克',
                'en' => 'Mozambique',
            ], [
                'iso' => 'MM',
                'iso3' => 'MMR',
                'zh' => '缅甸',
                'en' => 'Myanmar',
            ], [
                'iso' => 'NR',
                'iso3' => 'NRU',
                'zh' => '瑙鲁',
                'en' => 'Nauru',
            ], [
                'iso' => 'NP',
                'iso3' => 'NPL',
                'zh' => '尼泊尔',
                'en' => 'Nepal',
            ], [
                'iso' => 'NC',
                'iso3' => 'NCL',
                'zh' => '新喀里多尼亚',
                'en' => 'New Caledonia',
            ], [
                'iso' => 'NZ',
                'iso3' => 'NZL',
                'zh' => '新西兰',
                'en' => 'New Zealand',
            ], [
                'iso' => 'NI',
                'iso3' => 'NIC',
                'zh' => '尼加拉瓜',
                'en' => 'Nicaragua',
            ], [
                'iso' => 'NE',
                'iso3' => 'NER',
                'zh' => '尼日尔',
                'en' => 'Niger',
            ], [
                'iso' => 'NG',
                'iso3' => 'NGA',
                'zh' => '尼日利亚',
                'en' => 'Nigeria',
            ], [
                'iso' => 'NU',
                'iso3' => 'NIU',
                'zh' => '纽埃',
                'en' => 'Niue',
            ], [
                'iso' => 'NF',
                'iso3' => 'NFK',
                'zh' => '诺福克岛',
                'en' => 'Norfolk Island',
            ], [
                'iso' => 'KP',
                'iso3' => 'PRK',
                'zh' => '朝鲜',
                'en' => 'Korea (Democratic People\'s Republic of)',
            ], [
                'iso' => 'MP',
                'iso3' => 'MNP',
                'zh' => '北马里亚纳群岛',
                'en' => 'Northern Mariana Islands',
            ], [
                'iso' => 'OM',
                'iso3' => 'OMN',
                'zh' => '阿曼',
                'en' => 'Oman',
            ], [
                'iso' => 'PK',
                'iso3' => 'PAK',
                'zh' => '巴基斯坦',
                'en' => 'Pakistan',
            ], [
                'iso' => 'PW',
                'iso3' => 'PLW',
                'zh' => '帕劳',
                'en' => 'Palau',
            ], [
                'iso' => 'PS',
                'iso3' => 'PSE',
                'zh' => '巴勒斯坦',
                'en' => 'Palestine, State of',
            ], [
                'iso' => 'PA',
                'iso3' => 'PAN',
                'zh' => '巴拿马',
                'en' => 'Panama',
            ], [
                'iso' => 'PG',
                'iso3' => 'PNG',
                'zh' => '巴布亚新几内亚',
                'en' => 'Papua New Guinea',
            ], [
                'iso' => 'PY',
                'iso3' => 'PRY',
                'zh' => '巴拉圭',
                'en' => 'Paraguay',
            ], [
                'iso' => 'PE',
                'iso3' => 'PER',
                'zh' => '秘鲁',
                'en' => 'Peru',
            ], [
                'iso' => 'PH',
                'iso3' => 'PHL',
                'zh' => '菲律宾',
                'en' => 'Philippines',
            ], [
                'iso' => 'PN',
                'iso3' => 'PCN',
                'zh' => '皮特凯恩群岛',
                'en' => 'Pitcairn',
            ], [
                'iso' => 'PR',
                'iso3' => 'PRI',
                'zh' => '波多黎各',
                'en' => 'Puerto Rico',
            ], [
                'iso' => 'QA',
                'iso3' => 'QAT',
                'zh' => '卡塔尔',
                'en' => 'Qatar',
            ], [
                'iso' => 'XK',
                'iso3' => 'KOS',
                'zh' => '科索沃',
                'en' => 'Republic of Kosovo',
            ], [
                'iso' => 'RE',
                'iso3' => 'REU',
                'zh' => '留尼汪',
                'en' => 'Réunion',
            ], [
                'iso' => 'RU',
                'iso3' => 'RUS',
                'zh' => '俄罗斯',
                'en' => 'Russian Federation',
            ], [
                'iso' => 'RW',
                'iso3' => 'RWA',
                'zh' => '卢旺达',
                'en' => 'Rwanda',
            ], [
                'iso' => 'BL',
                'iso3' => 'BLM',
                'zh' => '圣巴泰勒米',
                'en' => 'Saint Barthélemy',
            ], [
                'iso' => 'SH',
                'iso3' => 'SHN',
                'zh' => '圣赫勒拿',
                'en' => 'Saint Helena, Ascension and Tristan da Cunha',
            ], [
                'iso' => 'KN',
                'iso3' => 'KNA',
                'zh' => '圣基茨和尼维斯',
                'en' => 'Saint Kitts and Nevis',
            ], [
                'iso' => 'LC',
                'iso3' => 'LCA',
                'zh' => '圣卢西亚',
                'en' => 'Saint Lucia',
            ], [
                'iso' => 'MF',
                'iso3' => 'MAF',
                'zh' => '圣马丁（法属）',
                'en' => 'Saint Martin (French part)',
            ], [
                'iso' => 'PM',
                'iso3' => 'SPM',
                'zh' => '圣皮埃尔和密克隆',
                'en' => 'Saint Pierre and Miquelon',
            ], [
                'iso' => 'VC',
                'iso3' => 'VCT',
                'zh' => '圣文森特和格林纳丁斯',
                'en' => 'Saint Vincent and the Grenadines',
            ], [
                'iso' => 'WS',
                'iso3' => 'WSM',
                'zh' => '萨摩亚',
                'en' => 'Samoa',
            ], [
                'iso' => 'SM',
                'iso3' => 'SMR',
                'zh' => '圣马力诺',
                'en' => 'San Marino',
            ], [
                'iso' => 'ST',
                'iso3' => 'STP',
                'zh' => '圣多美和普林西比',
                'en' => 'Sao Tome and Principe',
            ], [
                'iso' => 'SA',
                'iso3' => 'SAU',
                'zh' => '沙特阿拉伯',
                'en' => 'Saudi Arabia',
            ], [
                'iso' => 'SN',
                'iso3' => 'SEN',
                'zh' => '塞内加尔',
                'en' => 'Senegal',
            ], [
                'iso' => 'RS',
                'iso3' => 'SRB',
                'zh' => '塞尔维亚',
                'en' => 'Serbia',
            ], [
                'iso' => 'SC',
                'iso3' => 'SYC',
                'zh' => '塞舌尔',
                'en' => 'Seychelles',
            ], [
                'iso' => 'SL',
                'iso3' => 'SLE',
                'zh' => '塞拉利昂',
                'en' => 'Sierra Leone',
            ], [
                'iso' => 'SG',
                'iso3' => 'SGP',
                'zh' => '新加坡',
                'en' => 'Singapore',
            ], [
                'iso' => 'SX',
                'iso3' => 'SXM',
                'zh' => '圣马丁（荷属）',
                'en' => 'Sint Maarten (Dutch part)',
            ], [
                'iso' => 'SB',
                'iso3' => 'SLB',
                'zh' => '所罗门群岛',
                'en' => 'Solomon Islands',
            ], [
                'iso' => 'SO',
                'iso3' => 'SOM',
                'zh' => '索马里',
                'en' => 'Somalia',
            ], [
                'iso' => 'ZA',
                'iso3' => 'ZAF',
                'zh' => '南非',
                'en' => 'South Africa',
            ], [
                'iso' => 'GS',
                'iso3' => 'SGS',
                'zh' => '南乔治亚和南桑威奇群岛',
                'en' => 'South Georgia and the South Sandwich Islands',
            ], [
                'iso' => 'KR',
                'iso3' => 'KOR',
                'zh' => '韩国',
                'en' => 'Korea (Republic of)',
            ], [
                'iso' => 'SS',
                'iso3' => 'SSD',
                'zh' => '南苏丹',
                'en' => 'South Sudan',
            ], [
                'iso' => 'LK',
                'iso3' => 'LKA',
                'zh' => '斯里兰卡',
                'en' => 'Sri Lanka',
            ], [
                'iso' => 'SD',
                'iso3' => 'SDN',
                'zh' => '苏丹',
                'en' => 'Sudan',
            ], [
                'iso' => 'SR',
                'iso3' => 'SUR',
                'zh' => '苏里南',
                'en' => 'Suriname',
            ], [
                'iso' => 'SJ',
                'iso3' => 'SJM',
                'zh' => '斯瓦尔巴和扬马延',
                'en' => 'Svalbard and Jan Mayen',
            ], [
                'iso' => 'SZ',
                'iso3' => 'SWZ',
                'zh' => '斯威士兰',
                'en' => 'Swaziland',
            ], [
                'iso' => 'SY',
                'iso3' => 'SYR',
                'zh' => '叙利亚',
                'en' => 'Syrian Arab Republic',
            ], [
                'iso' => 'TW',
                'iso3' => 'TWN',
                'zh' => '中国台湾',
                'en' => 'Taiwan',
            ], [
                'iso' => 'TJ',
                'iso3' => 'TJK',
                'zh' => '塔吉克斯坦',
                'en' => 'Tajikistan',
            ], [
                'iso' => 'TZ',
                'iso3' => 'TZA',
                'zh' => '坦桑尼亚',
                'en' => 'Tanzania, United Republic of',
            ], [
                'iso' => 'TH',
                'iso3' => 'THA',
                'zh' => '泰国',
                'en' => 'Thailand',
            ], [
                'iso' => 'TL',
                'iso3' => 'TLS',
                'zh' => '东帝汶',
                'en' => 'Timor-Leste',
            ], [
                'iso' => 'TG',
                'iso3' => 'TGO',
                'zh' => '多哥',
                'en' => 'Togo',
            ], [
                'iso' => 'TK',
                'iso3' => 'TKL',
                'zh' => '托克劳',
                'en' => 'Tokelau',
            ], [
                'iso' => 'TO',
                'iso3' => 'TON',
                'zh' => '汤加',
                'en' => 'Tonga',
            ], [
                'iso' => 'TT',
                'iso3' => 'TTO',
                'zh' => '特立尼达和多巴哥',
                'en' => 'Trinidad and Tobago',
            ], [
                'iso' => 'TN',
                'iso3' => 'TUN',
                'zh' => '突尼斯',
                'en' => 'Tunisia',
            ], [
                'iso' => 'TM',
                'iso3' => 'TKM',
                'zh' => '土库曼斯坦',
                'en' => 'Turkmenistan',
            ], [
                'iso' => 'TC',
                'iso3' => 'TCA',
                'zh' => '特克斯和凯科斯群岛',
                'en' => 'Turks and Caicos Islands',
            ], [
                'iso' => 'TV',
                'iso3' => 'TUV',
                'zh' => '图瓦卢',
                'en' => 'Tuvalu',
            ], [
                'iso' => 'UG',
                'iso3' => 'UGA',
                'zh' => '乌干达',
                'en' => 'Uganda',
            ], [
                'iso' => 'UA',
                'iso3' => 'UKR',
                'zh' => '乌克兰',
                'en' => 'Ukraine',
            ], [
                'iso' => 'UY',
                'iso3' => 'URY',
                'zh' => '乌拉圭',
                'en' => 'Uruguay',
            ], [
                'iso' => 'UZ',
                'iso3' => 'UZB',
                'zh' => '乌兹别克斯坦',
                'en' => 'Uzbekistan',
            ], [
                'iso' => 'VU',
                'iso3' => 'VUT',
                'zh' => '瓦努阿图',
                'en' => 'Vanuatu',
            ], [
                'iso' => 'VE',
                'iso3' => 'VEN',
                'zh' => '委内瑞拉',
                'en' => 'Venezuela (Bolivarian Republic of)',
            ], [
                'iso' => 'VN',
                'iso3' => 'VNM',
                'zh' => '越南',
                'en' => 'Viet Nam',
            ], [
                'iso' => 'WF',
                'iso3' => 'WLF',
                'zh' => '瓦利斯和富图纳',
                'en' => 'Wallis and Futuna',
            ], [
                'iso' => 'EH',
                'iso3' => 'ESH',
                'zh' => '西撒哈拉',
                'en' => 'Western Sahara',
            ], [
                'iso' => 'YE',
                'iso3' => 'YEM',
                'zh' => '也门',
                'en' => 'Yemen',
            ], [
                'iso' => 'ZM',
                'iso3' => 'ZMB',
                'zh' => '赞比亚',
                'en' => 'Zambia',
            ], [
                'iso' => 'ZW',
                'iso3' => 'ZWE',
                'zh' => '津巴布韦',
                'en' => 'Zimbabwe',
            ],
        ];
    }
}
