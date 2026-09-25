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
class Migration1561370284AddImportExportProductProfile extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1561370284;
    }

    public function update(Connection $connection): void
    {
        $mapping = [];

        $fields = [
            'name.de-DE',
            'name.en-GB',
            'stock',
            'manufacturer.id',
            'tax.id',
            'price.net',
            'price.gross',
            'price.linked',
            'productNumber',
            'releaseDate',
            'categories.id',
        ];

        foreach ($fields as $fieldName) {
            $mapping[] = [
                'fileField' => $fieldName,
                'entityField' => $fieldName,
                'valueSubstitutions' => [],
            ];
        }

        $connection->insert('import_export_profile', [
            'id' => Uuid::randomBytes(),
            'name' => 'Default product',
            'system_default' => 1,
            'source_entity' => 'product',
            'file_type' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
            'mapping' => json_encode($mapping, \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
