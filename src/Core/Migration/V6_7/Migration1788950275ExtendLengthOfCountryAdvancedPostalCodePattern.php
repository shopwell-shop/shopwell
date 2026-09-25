<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1788950275ExtendLengthOfCountryAdvancedPostalCodePattern extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1788950275;
    }

    public function update(Connection $connection): void
    {
        $column = TableHelper::getColumnOfTable(
            $connection,
            'country',
            'advanced_postal_code_pattern'
        );

        if ($column->type !== Types::STRING || $column->length === null || $column->length >= 1024) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `country`
            MODIFY COLUMN `advanced_postal_code_pattern` VARCHAR(1024) NULL
        ');
    }
}
