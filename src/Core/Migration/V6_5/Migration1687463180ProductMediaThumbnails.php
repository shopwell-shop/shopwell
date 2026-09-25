<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\DataAbstractionLayer\Util\StatementHelper;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Migration\Traits\EnsureThumbnailSizesTrait;

/**
 * @internal
 */
#[Package('framework')]
class Migration1687463180ProductMediaThumbnails extends MigrationStep
{
    use EnsureThumbnailSizesTrait;

    public function getCreationTimestamp(): int
    {
        return 1687463180;
    }

    public function update(Connection $connection): void
    {
        $thumbnailSizes = [
            ['width' => 280, 'height' => 280],
        ];

        $thumbnailSizeIds = $this->ensureThumbnailSizes($thumbnailSizes, $connection);

        $configurationId = $connection->fetchOne(
            'SELECT media_folder_configuration_id FROM media_folder WHERE name = :name',
            ['name' => 'Product Media']
        );

        if (!$configurationId) {
            return;
        }

        $statement = $connection->prepare('
                    REPLACE INTO `media_folder_configuration_media_thumbnail_size` (`media_folder_configuration_id`, `media_thumbnail_size_id`)
                    VALUES (:folderConfigurationId, :thumbnailSizeId)
                ');

        foreach ($thumbnailSizeIds as $thumbnailSizeId) {
            StatementHelper::executeStatement($statement, [
                'folderConfigurationId' => $configurationId,
                'thumbnailSizeId' => $thumbnailSizeId,
            ]);
        }

        $this->registerIndexer($connection, 'media_folder_configuration.indexer');
    }
}
