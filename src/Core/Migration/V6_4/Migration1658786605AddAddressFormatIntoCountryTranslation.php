<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\System\Country\CountryDefinition;

/**
 * @internal
 */
#[Package('framework')]
class Migration1658786605AddAddressFormatIntoCountryTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1658786605;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'country_translation', 'address_format')) {
            return;
        }

        $addressFormat = json_encode(CountryDefinition::DEFAULT_ADDRESS_FORMAT, \JSON_THROW_ON_ERROR);

        $connection->executeStatement('
            ALTER TABLE `country_translation` ADD COLUMN `address_format` JSON NULL AFTER `name`;
        ');

        $connection->update('country_translation', [
            'address_format' => $addressFormat,
        ], ['address_format' => null]);

        $connection->executeStatement(
            'ALTER TABLE `country_translation` MODIFY `address_format` JSON NOT NULL;'
        );
    }
}
