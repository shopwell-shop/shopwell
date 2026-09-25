<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Media\Api;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\Api\MediaVideoCoverController;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Content\Media\MediaException;
use Shopwell\Core\Content\Media\MediaType\VideoType;
use Shopwell\Core\Content\Test\Media\MediaFixtures;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
class MediaVideoCoverControllerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use IntegrationTestBehaviour;
    use MediaFixtures;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->setFixtureContext($this->context);

        $this->mediaRepository = static::getContainer()->get('media.repository');
    }

    public function testAssignVideoCoverUpdatesMetaData(): void
    {
        $cover = $this->getPng();
        $video = $this->createVideoMedia();

        $response = $this->getController()->assignVideoCover(
            $video->getId(),
            new Request([], ['coverMediaId' => $cover->getId()]),
            $this->context,
        );

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $reloaded = $this->getMediaEntity($video->getId());
        static::assertSame($cover->getId(), $reloaded->getMetaData()['video']['coverMediaId'] ?? null);
    }

    public function testAssignVideoCoverReturnsBadRequestOnInvalidPayload(): void
    {
        $video = $this->createVideoMedia();

        $this->expectExceptionObject(MediaException::invalidRequestParameter('coverMediaId'));

        $this->getController()->assignVideoCover(
            $video->getId(),
            new Request([], ['coverMediaId' => ['invalid']]),
            $this->context,
        );
    }

    public function testAssignVideoCoverReturnsBadRequestOnNonStringCoverMediaId(): void
    {
        $video = $this->createVideoMedia();

        $this->expectExceptionObject(MediaException::invalidRequestParameter('coverMediaId'));

        $this->getController()->assignVideoCover(
            $video->getId(),
            new Request([], ['coverMediaId' => 123]),
            $this->context,
        );
    }

    private function getController(): MediaVideoCoverController
    {
        $controller = static::getContainer()->get(MediaVideoCoverController::class);
        static::assertInstanceOf(MediaVideoCoverController::class, $controller);

        return $controller;
    }

    private function createVideoMedia(): MediaEntity
    {
        $id = Uuid::randomHex();

        $this->mediaRepository->create([[
            'id' => $id,
            'mimeType' => 'video/mp4',
            'fileExtension' => 'mp4',
            'fileName' => 'video-' . $id,
            'fileSize' => 1024,
            'mediaType' => new VideoType(),
        ]], $this->context);

        return $this->getMediaEntity($id);
    }

    private function getMediaEntity(string $id): MediaEntity
    {
        $entity = $this->mediaRepository->search(new Criteria([$id]), $this->context)->getEntities()->first();

        static::assertNotNull($entity, \sprintf('Media entity "%s" not found', $id));
        static::assertInstanceOf(MediaEntity::class, $entity);

        return $entity;
    }
}
