<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Media\Aggregate\MediaFolderConfigurationThumbnailSize;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationCollection;
use Shopwell\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Shopwell\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Shopwell\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class MediaFolderConfigurationMediaThumbnailSizeTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCreateConfiguration(): void
    {
        $context = Context::createDefaultContext();
        /**
         * @var EntityRepository<MediaFolderConfigurationCollection> $repository
         */
        $repository = static::getContainer()->get('media_folder_configuration.repository');

        $configurationId = Uuid::randomHex();
        $sizeId = Uuid::randomHex();

        $repository->create([
            [
                'id' => $configurationId,
                'createThumbnails' => true,
                'mediaThumbnailSizes' => [
                    [
                        'id' => $sizeId,
                        'width' => 100,
                        'height' => 100,
                    ],
                ],
            ],
        ], $context);

        $criteria = new Criteria([$configurationId]);
        $criteria->addAssociation('mediaThumbnailSizes');

        $read = $repository->search($criteria, $context)->getEntities();
        $configuration = $read->get($configurationId);

        static::assertInstanceOf(MediaFolderConfigurationEntity::class, $configuration);
        $sizes = $configuration->getMediaThumbnailSizes();
        static::assertInstanceOf(MediaThumbnailSizeCollection::class, $sizes);
        static::assertCount(1, $sizes);
        static::assertNotNull($sizes->get($sizeId));
    }

    public function testCreateThumbnailSize(): void
    {
        $context = Context::createDefaultContext();
        /**
         * @var EntityRepository<MediaThumbnailSizeCollection> $repository
         */
        $repository = static::getContainer()->get('media_thumbnail_size.repository');

        $sizeId = Uuid::randomHex();
        $confId = Uuid::randomHex();

        $repository->upsert([
            [
                'id' => $sizeId,
                'width' => 100,
                'height' => 100,
                'mediaFolderConfigurations' => [
                    [
                        'id' => $confId,
                        'createThumbnails' => true,
                    ],
                ],
            ],
        ], $context);

        $criteria = (new Criteria())
            ->addAssociation('mediaFolderConfigurations');

        $search = $repository->search($criteria, $context);

        $size = $search->getEntities()->get($sizeId);
        static::assertInstanceOf(MediaThumbnailSizeEntity::class, $size);
        $configurations = $size->getMediaFolderConfigurations();
        static::assertInstanceOf(MediaFolderConfigurationCollection::class, $configurations);
        static::assertCount(1, $configurations);
        static::assertNotNull($configurations->get($confId));
    }
}
