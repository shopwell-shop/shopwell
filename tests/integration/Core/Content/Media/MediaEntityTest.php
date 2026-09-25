<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Media;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopwell\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Content\Test\Media\MediaFixtures;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('discovery')]
class MediaEntityTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MediaFixtures;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $repository;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('media.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testWriteReadMinimalFields(): void
    {
        $media = $this->getEmptyMedia();

        $criteria = $this->getIdCriteria($media->getId());
        $result = $this->repository->search($criteria, $this->context);
        $media = $result->getEntities()->first();

        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame($media->getId(), $media->getId());
    }

    public function testThumbnailsAreConvertedToStructWhenFetchedFromDb(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getMediaWithThumbnail();

        $criteria = $this->getIdCriteria($media->getId());
        $searchResult = $this->repository->search($criteria, $this->context);
        $fetchedMedia = $searchResult->getEntities()->get($media->getId());

        static::assertInstanceOf(MediaEntity::class, $fetchedMedia);
        static::assertInstanceOf(MediaThumbnailCollection::class, $fetchedMedia->getThumbnails());

        $persistedThumbnail = $fetchedMedia->getThumbnails()->first();
        static::assertInstanceOf(MediaThumbnailEntity::class, $persistedThumbnail);
        static::assertSame(200, $persistedThumbnail->getWidth());
        static::assertSame(200, $persistedThumbnail->getHeight());
    }

    public function testDeleteMediaWithTags(): void
    {
        $media = $this->getEmptyMedia();

        $this->repository->update([
            [
                'id' => $media->getId(),
                'tags' => [['name' => 'test tag']],
            ],
        ], $this->context);

        $this->repository->delete([['id' => $media->getId()]], $this->context);
    }

    private function getIdCriteria(string $mediaId): Criteria
    {
        $criteria = new Criteria();
        $criteria->setOffset(0);
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('media.id', $mediaId));

        return $criteria;
    }
}
