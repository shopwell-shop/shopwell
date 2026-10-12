<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * Aligns the chinese address format with the local conventions: postal codes play almost no
 * role, so they become optional, and addresses render in the province-city-district order
 * with the detailed address following the regions.
 *
 * @internal
 */
#[Package('fundamentals@discovery')]
class Migration1791799469ChinaAddressFormat extends MigrationStep
{
    private const CHINA_ISO = 'CN';

    private const CHINA_ADDRESS_FORMAT = [
        ['address/company', 'symbol/dash', 'address/department'],
        ['address/name'],
        ['address/country_state', 'address/city', 'address/district'],
        ['address/street'],
        ['address/zipcode'],
    ];

    public function getCreationTimestamp(): int
    {
        return 1791799469;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE `country` SET `postal_code_required` = 0 WHERE `iso` = :iso AND `postal_code_required` = 1',
            ['iso' => self::CHINA_ISO]
        );

        $connection->executeStatement(
            'UPDATE `country_translation` `ct`
             INNER JOIN `country` `c` ON `c`.`id` = `ct`.`country_id`
             SET `ct`.`address_format` = :addressFormat
             WHERE `c`.`iso` = :iso',
            [
                'addressFormat' => json_encode(self::CHINA_ADDRESS_FORMAT, \JSON_THROW_ON_ERROR),
                'iso' => self::CHINA_ISO,
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth migration, nothing destructive to do
    }
}
