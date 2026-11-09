<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1614249488ChangeProductSortingsToCheapestPrice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1614249488;
    }

    public function update(Connection $connection): void
    {
        $this->migrateSortings($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'product', 'listing_prices');
    }

    private function migrateSortings(Connection $connection): void
    {
        $sortings = $connection->fetchAllAssociative('SELECT id, fields FROM product_sorting WHERE fields IS NOT NULL');

        foreach ($sortings as $sorting) {
            if (!isset($sorting['id'])) {
                continue;
            }
            if (!isset($sorting['fields'])) {
                continue;
            }

            $id = $sorting['id'];
            $fields = json_decode((string) $sorting['fields'], true, 512, \JSON_THROW_ON_ERROR);
            $update = false;

            foreach ($fields as &$field) {
                if (!isset($field['field'])) {
                    continue;
                }
                if ($field['field'] !== 'product.listingPrices') {
                    continue;
                }
                $field['field'] = 'product.cheapestPrice';
                $update = true;
            }

            if ($update) {
                $connection->executeStatement(
                    '
                        UPDATE product_sorting
                        SET fields = :fields
                        WHERE id = :id
                    ',
                    ['fields' => json_encode($fields, \JSON_THROW_ON_ERROR), 'id' => $id]
                );
            }
        }
    }
}
