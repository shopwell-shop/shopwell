<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_5;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\Traits\ImportTranslationsTrait;
use Shopwell\Core\Migration\Traits\Translations;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
class Migration1677470540AddProvincesForCanada extends MigrationStep
{
    use ImportTranslationsTrait;

    public const CANADA_STATES = [
        // 10 Provinces
        [
            'nameEN' => 'Ontario',
            'nameZH' => '安大略省',
            'shortCode' => 'CA-ON',
        ],
        [
            'nameEN' => 'Quebec',
            'nameZH' => '魁北克省',
            'shortCode' => 'CA-QC',
        ],
        [
            'nameEN' => 'Nova Scotia',
            'nameZH' => '新斯科舍省',
            'shortCode' => 'CA-NS',
        ],
        [
            'nameEN' => 'New Brunswick',
            'nameZH' => '新不伦瑞克省',
            'shortCode' => 'CA-NB',
        ],
        [
            'nameEN' => 'Manitoba',
            'nameZH' => '曼尼托巴省',
            'shortCode' => 'CA-MB',
        ],
        [
            'nameEN' => 'British Columbia',
            'nameZH' => '不列颠哥伦比亚省',
            'shortCode' => 'CA-BC',
        ],
        [
            'nameEN' => 'Prince Edward Island',
            'nameZH' => '爱德华王子岛省',
            'shortCode' => 'CA-PE',
        ],
        [
            'nameEN' => 'Saskatchewan',
            'nameZH' => '萨斯喀彻温省',
            'shortCode' => 'CA-SK',
        ],
        [
            'nameEN' => 'Alberta',
            'nameZH' => '艾伯塔省',
            'shortCode' => 'CA-AB',
        ],
        [
            'nameEN' => 'Newfoundland and Labrador',
            'nameZH' => '纽芬兰与拉布拉多省',
            'shortCode' => 'CA-NL',
        ],
        // 3 Territories
        [
            'nameEN' => 'Northwest Territories',
            'nameZH' => '西北地区',
            'shortCode' => 'CA-NT',
        ],
        [
            'nameEN' => 'Yukon',
            'nameZH' => '育空地区',
            'shortCode' => 'CA-YT',
        ],
        [
            'nameEN' => 'Nunavut',
            'nameZH' => '努纳武特地区',
            'shortCode' => 'CA-NU',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1677470540;
    }

    public function update(Connection $connection): void
    {
        $countryId = $connection->fetchOne('SELECT id from country WHERE iso = \'CA\' AND iso3 = \'CAN\'');

        if (!$countryId) {
            return;
        }

        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $queue = new MultiInsertQueryQueue($connection, \count(self::CANADA_STATES), false, true);
        $countryStateTranslations = [];

        $shortCodes = array_map(static fn ($state) => $state['shortCode'], self::CANADA_STATES);

        $existStates = $connection->fetchFirstColumn(
            'SELECT short_code FROM country_state WHERE short_code IN (:shortCodes)',
            ['shortCodes' => $shortCodes],
            ['shortCodes' => ArrayParameterType::STRING]
        );

        foreach (self::CANADA_STATES as $state) {
            // skip if exist state
            if (\in_array($state['shortCode'], $existStates, true)) {
                continue;
            }

            $countryStateId = Uuid::randomBytes();

            $countryStateData = [
                'id' => $countryStateId,
                'country_id' => $countryId,
                'short_code' => $state['shortCode'],
                'position' => 1,
                'active' => 1,
                'created_at' => $createdAt,
            ];

            $queue->addInsert('country_state', $countryStateData);

            $countryStateTranslations[] = new Translations([
                'country_state_id' => $countryStateId,
                'name' => $state['nameZH'],
            ], [
                'country_state_id' => $countryStateId,
                'name' => $state['nameEN'],
            ]);
        }

        $queue->execute();

        foreach ($countryStateTranslations as $translations) {
            $this->importTranslation('country_state_translation', $translations, $connection);
        }
    }
}
