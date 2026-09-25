<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\ImportExport\ImportExportProfileTranslationDefinition;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\Traits\ImportTranslationsTrait;
use Shopwell\Core\Migration\Traits\Translations;

/**
 * @internal
 */
#[Package('framework')]
class Migration1625831469AddImportExportCrossSellingProfile extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1625831469;
    }

    public function update(Connection $connection): void
    {
        $id = Uuid::randomBytes();

        $connection->insert('import_export_profile', [
            'id' => $id,
            'name' => 'Default cross-selling',
            'system_default' => 1,
            'source_entity' => 'product_cross_selling',
            'file_type' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
            'mapping' => json_encode([
                ['key' => 'id', 'mappedKey' => 'id'],
                ['key' => 'translations.DEFAULT.name', 'mappedKey' => 'name'],
                ['key' => 'productId', 'mappedKey' => 'product_id'],
                ['key' => 'active', 'mappedKey' => 'active'],
                ['key' => 'position', 'mappedKey' => 'position'],
                ['key' => 'limit', 'mappedKey' => 'limit'],
                ['key' => 'type', 'mappedKey' => 'type'],
                ['key' => 'sortBy', 'mappedKey' => 'sort_by'],
                ['key' => 'sortDirection', 'mappedKey' => 'sort_direction'],
                ['key' => 'assignedProducts', 'mappedKey' => 'assigned_products'],
            ]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $translations = new Translations(
            [
                'import_export_profile_id' => $id,
                'label' => 'Standardprofil Cross-Selling',
            ],
            [
                'import_export_profile_id' => $id,
                'label' => 'Default cross-selling',
            ]
        );

        $this->importTranslation(ImportExportProfileTranslationDefinition::ENTITY_NAME, $translations, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
