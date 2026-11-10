<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Content\Blog\Service\BlogService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogService::class)]
class BlogServiceTest extends TestCase
{
    private Context $context;

    private string $blogId;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->blogId = Uuid::randomHex();
    }

    public function testPublishingAnUndatedEntrySetsTheDateToNow(): void
    {
        $repository = $this->repository($this->blog(publishedAt: null));

        $this->service($repository)->publish($this->blogId, $this->context);

        $payload = $repository->updates[0][0];

        static::assertSame($this->blogId, $payload['id']);
        static::assertTrue($payload['active']);
        static::assertInstanceOf(\DateTimeInterface::class, $payload['publishedAt']);
        static::assertSame('2026-01-01 10:00:00', $payload['publishedAt']->format('Y-m-d H:i:s'));
    }

    public function testPublishingADatedEntryWritesNothingAtAll(): void
    {
        $publishedAt = new \DateTimeImmutable('2026-01-01 09:00:00', new \DateTimeZone('UTC'));
        $blog = $this->blog(publishedAt: $publishedAt);
        $blog->active = true;
        $repository = $this->repository($blog);

        $this->service($repository)->publish($this->blogId, $this->context);

        // no write means no bumped updated_at and no written event for every listener to process
        static::assertSame([], $repository->updates);
    }

    public function testPublishingAnUnknownEntryIsRejected(): void
    {
        $repository = StaticEntityRepository::of(BlogCollection::class, [new BlogCollection()]);

        try {
            $this->service($repository)->publish($this->blogId, $this->context);
            static::fail('Publishing an entry that does not exist must not write anything.');
        } catch (BlogException $exception) {
            static::assertSame(BlogException::BLOG_NOT_FOUND, $exception->getErrorCode());
            static::assertSame(['blogId' => $this->blogId], $exception->getParameters());
        }

        static::assertSame([], $repository->updates);
    }

    public function testSchedulingWritesTheGivenMomentWithoutTouchingAnythingElse(): void
    {
        $repository = $this->repository($this->blog(publishedAt: null));
        $moment = new \DateTimeImmutable('2026-12-24 06:00:00', new \DateTimeZone('UTC'));

        $this->service($repository)->schedule($this->blogId, $moment, $this->context);

        static::assertSame([
            [
                'id' => $this->blogId,
                'publishedAt' => $moment,
                'active' => true,
            ],
        ], $repository->updates[0]);
    }

    public function testPublishingADatedDraftPreservesItsDateAndActivatesIt(): void
    {
        $repository = $this->repository($this->blog(publishedAt: new \DateTimeImmutable('2025-12-24')));

        $this->service($repository)->publish($this->blogId, $this->context);

        static::assertSame([[['id' => $this->blogId, 'active' => true]]], $repository->updates);
    }

    public function testUnpublishingPreservesThePublicationDate(): void
    {
        $blog = $this->blog(publishedAt: new \DateTimeImmutable('2025-12-24'));
        $blog->active = true;
        $repository = $this->repository($blog);

        $this->service($repository)->unpublish($this->blogId, $this->context);

        static::assertSame([[['id' => $this->blogId, 'active' => false]]], $repository->updates);
    }

    public function testUnpublishingADraftDoesNotWriteAgain(): void
    {
        $repository = $this->repository($this->blog(publishedAt: null));

        $this->service($repository)->unpublish($this->blogId, $this->context);

        static::assertSame([], $repository->updates);
    }

    /**
     * @param StaticEntityRepository<BlogCollection> $repository
     */
    private function service(StaticEntityRepository $repository): BlogService
    {
        return new BlogService($repository, new MockClock('2026-01-01 10:00:00', 'UTC'));
    }

    /**
     * @return StaticEntityRepository<BlogCollection>
     */
    private function repository(BlogEntity $blog): StaticEntityRepository
    {
        return StaticEntityRepository::of(BlogCollection::class, [
            new BlogCollection([$blog]),
        ]);
    }

    private function blog(?\DateTimeImmutable $publishedAt): BlogEntity
    {
        $blog = new BlogEntity();
        $blog->id = $this->blogId;
        // the collection indexes by unique identifier, and the dal sets it on hydrate normally
        $blog->setUniqueIdentifier($this->blogId);
        $blog->title = 'An entry';
        $blog->slug = 'an-entry';
        $blog->publishedAt = $publishedAt;

        return $blog;
    }
}
