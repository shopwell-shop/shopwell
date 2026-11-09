<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1700746995ReplaceSortingOptionKeysWithSortingOptionIds extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700746995;
    }

    public function update(Connection $connection): void
    {
        $this->migrateSystemConfig($connection);
    }

    private function migrateSystemConfig(Connection $connection): void
    {
        $systemConfigEntries = $connection->fetchAllKeyValue(
            'SELECT id, configuration_value FROM system_config WHERE configuration_key = "core.listing.defaultSorting";'
        );

        foreach ($systemConfigEntries as $id => $configValue) {
            $config = json_decode($configValue, true);

            if (!\array_key_exists('_value', $config)) {
                continue;
            }

            $urlKey = $config['_value'];

            if (!isset($urlKey)) {
                continue;
            }

            $sortingId = $connection->fetchOne(
                'SELECT id FROM product_sorting WHERE url_key = :urlKey;',
                ['urlKey' => $urlKey]
            );

            // the results of invalid url keys are filtered here
            if (!$sortingId) {
                continue;
            }

            $connection->executeStatement(
                'UPDATE system_config SET configuration_value = :configValue WHERE id = :id;',
                ['configValue' => json_encode(['_value' => Uuid::fromBytesToHex($sortingId)]), 'id' => $id]
            );
        }
    }
}
