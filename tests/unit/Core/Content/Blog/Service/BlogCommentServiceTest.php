<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentEntity;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Content\Blog\Service\BlogCommentDraft;
use Shopwell\Core\Content\Blog\Service\BlogCommentFloodGuard;
use Shopwell\Core\Content\Blog\Service\BlogCommentService;
use Shopwell\Core\Content\Blog\Service\BlogSettings;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;

/**
 * The write path is the only place a reply is attached to something, and every check below exists
 * because the parent id arrives in a request body and can therefore be anything.
 *
 * The depth cases are the reason the walk up the chain is not a stored column: the same chain has to
 * answer "accepted" for the fifth level and "rejected" for the sixth, and it has to do so without a
 * second copy of the depth that can drift away from the rows.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogCommentService::class)]
class BlogCommentServiceTest extends TestCase
{
    private const BLOG_ID = 'blog-a';

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testAReplyThatBecomesTheFifthLevelIsAccepted(): void
    {
        $chain = $this->chain(5);
        $repository = $this->repository($chain);
        $parent = $chain['l4'];

        $id = $this->service($repository)->create($this->draft(parentId: $parent->id), $this->context);

        static::assertSame($parent->id, $repository->creates[0][0]['parentId']);
        static::assertSame($id, $repository->creates[0][0]['id']);
    }

    public function testAReplyThatWouldBecomeTheSixthLevelIsRejected(): void
    {
        $chain = $this->chain(5);
        $repository = $this->repository($chain);

        try {
            $this->service($repository)->create($this->draft(parentId: $chain['l5']->id), $this->context);
            static::fail('A sixth level must not be written.');
        } catch (BlogException $exception) {
            static::assertSame(BlogException::COMMENT_DEPTH_EXCEEDED, $exception->getErrorCode());
            static::assertSame(['maxDepth' => BlogCommentService::MAX_DEPTH], $exception->getParameters());
        }

        // rejected, and not silently attached to the fifth level instead
        static::assertSame([], $repository->creates);
    }

    public function testAParentOfAnotherEntryIsRejected(): void
    {
        $foreign = $this->comment('foreign', null, 'blog-b');
        $repository = $this->repository(['foreign' => $foreign]);

        try {
            $this->service($repository)->create($this->draft(parentId: 'foreign'), $this->context);
            static::fail('A reply must not cross into another entry.');
        } catch (BlogException $exception) {
            static::assertSame(BlogException::COMMENT_PARENT_FOREIGN_BLOG, $exception->getErrorCode());
            static::assertSame(
                ['parentId' => 'foreign', 'blogId' => self::BLOG_ID],
                $exception->getParameters()
            );
        }

        static::assertSame([], $repository->creates);
    }

    public function testAParentThatIsNotApprovedIsRejected(): void
    {
        $pending = $this->comment('pending', null, self::BLOG_ID, status: false);
        $repository = $this->repository(['pending' => $pending]);

        try {
            $this->service($repository)->create($this->draft(parentId: 'pending'), $this->context);
            static::fail('Replying to a comment nobody can read must not work.');
        } catch (BlogException $exception) {
            static::assertSame(BlogException::COMMENT_PARENT_NOT_APPROVED, $exception->getErrorCode());
        }

        static::assertSame([], $repository->creates);
    }

    public function testAParentThatDoesNotExistIsRejected(): void
    {
        $repository = $this->repository([]);

        try {
            $this->service($repository)->create($this->draft(parentId: 'gone'), $this->context);
            static::fail('A reply without a parent must not be written.');
        } catch (BlogException $exception) {
            static::assertSame(BlogException::COMMENT_PARENT_NOT_FOUND, $exception->getErrorCode());
        }

        static::assertSame([], $repository->creates);
    }

    public function testAModeratedShopStoresTheNewCommentAsPending(): void
    {
        $repository = $this->repository([]);

        $this->service($repository, autoPublish: false)->create($this->draft(), $this->context);

        static::assertFalse($repository->creates[0][0]['status']);
    }

    public function testAnAutoPublishingShopStoresTheNewCommentAsApproved(): void
    {
        $repository = $this->repository([]);

        $this->service($repository, autoPublish: true)->create($this->draft(), $this->context);

        static::assertTrue($repository->creates[0][0]['status']);
    }

    public function testTheRequestCannotChooseTheStatus(): void
    {
        $repository = $this->repository([]);

        $this->service($repository, autoPublish: false)->create($this->draft(), $this->context);

        // the payload is built field by field, so nothing the caller sent can leak into it
        static::assertSame(
            ['id', 'blogId', 'parentId', 'customerId', 'salesChannelId', 'languageId', 'externalUser', 'externalEmail', 'title', 'content', 'status'],
            array_keys($repository->creates[0][0])
        );
    }

    /**
     * A thread of the given depth: `l1` is the root, every further level answers the one before it.
     *
     * @return array<string, BlogCommentEntity>
     */
    private function chain(int $depth): array
    {
        $chain = [];

        for ($level = 1; $level <= $depth; ++$level) {
            $parentId = $level === 1 ? null : 'l' . ($level - 1);
            $chain['l' . $level] = $this->comment('l' . $level, $parentId, self::BLOG_ID);
        }

        return $chain;
    }

    /**
     * @param StaticEntityRepository<BlogCommentCollection> $repository
     */
    private function service(StaticEntityRepository $repository, bool $autoPublish = false): BlogCommentService
    {
        return new BlogCommentService(
            $repository,
            new BlogCommentFloodGuard(
                StaticEntityRepository::of(BlogCommentCollection::class, [[]]),
                new MockClock('2026-01-01 10:00:00', 'UTC'),
            ),
            new BlogSettings(new StaticSystemConfigService([
                BlogSettings::CONFIG_KEY_AUTO_PUBLISH_COMMENTS => $autoPublish,
            ])),
        );
    }

    private function draft(?string $parentId = null): BlogCommentDraft
    {
        return new BlogCommentDraft(
            blogId: self::BLOG_ID,
            parentId: $parentId,
            content: 'A long enough comment to be believable.',
            title: null,
            customerId: null,
            externalUser: 'A reader',
            externalEmail: 'reader@example.com',
            salesChannelId: 'sales-channel',
            languageId: 'language',
        );
    }

    /**
     * A repository that answers every lookup by id, no matter how often the chain walk asks. The
     * search callable re-arms itself, which is what a static list of results cannot do for a walk of
     * unknown length.
     *
     * @param array<string, BlogCommentEntity> $byId
     *
     * @return StaticEntityRepository<BlogCommentCollection>
     */
    private function repository(array $byId): StaticEntityRepository
    {
        $repository = StaticEntityRepository::of(BlogCommentCollection::class);

        $search = static function (Criteria $criteria, Context $context, StaticEntityRepository $repository) use (&$search, $byId): BlogCommentCollection {
            $repository->addSearch($search);

            $ids = $criteria->getIds();
            $id = $ids[0] ?? null;

            if (!\is_string($id) || !isset($byId[$id])) {
                return new BlogCommentCollection();
            }

            return new BlogCommentCollection([$byId[$id]]);
        };

        $repository->addSearch($search);

        return $repository;
    }

    private function comment(string $id, ?string $parentId, string $blogId, bool $status = true): BlogCommentEntity
    {
        $comment = new BlogCommentEntity();
        $comment->id = $id;
        // the collection indexes by unique identifier, and the dal sets it on hydrate normally
        $comment->setUniqueIdentifier($id);
        $comment->blogId = $blogId;
        $comment->parentId = $parentId;
        $comment->salesChannelId = 'sales-channel';
        $comment->languageId = 'language';
        $comment->content = 'A comment';
        $comment->status = $status;
        $comment->setCreatedAt(new \DateTimeImmutable('2026-01-01 10:00:00', new \DateTimeZone('UTC')));

        return $comment;
    }
}
