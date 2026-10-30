<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Service;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogAuthor\BlogAuthorEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategoryAssignment\BlogCategoryAssignmentEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Shopwell\Core\Content\Blog\Aggregate\BlogComment\BlogCommentEntity;
use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Content\Blog\Service\BlogCommentTree;
use Shopwell\Core\Content\Blog\Service\BlogDetailReader;
use Shopwell\Core\Content\Blog\Service\BlogReadingTimeEstimator;
use Shopwell\Core\Content\Blog\Service\BlogSettings;
use Shopwell\Core\Content\Blog\Service\BlogVisibilityGuard;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\HtmlSanitizer;
use Shopwell\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * The reader decides what a detail page contains, and two of those decisions are not visible in the
 * entity: a scheduled entry is answered with the same 404 as a missing one, and a switched-off comment
 * thread must not cost a query at all. The table of contents is the other half - its anchors have to
 * be injected into the body with the ids the toc links to, or every entry in it is a dead link.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogDetailReader::class)]
class BlogDetailReaderTest extends TestCase
{
    #[AllowMockObjectsWithoutExpectations]
    public function testAMissingEntryIsAnsweredWithNotFound(): void
    {
        $reader = $this->reader(
            blogRepository: $this->blogRepositoryReturning(new BlogCollection()),
            settings: static::createStub(BlogSettings::class),
        );

        $this->expectExceptionObject(BlogException::notFound('missing-id'));

        $reader->read('missing-id', new Request(), Generator::generateSalesChannelContext());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAScheduledEntryIsAnsweredWithTheSameNotFoundAsAMissingOne(): void
    {
        $blog = $this->blog('blog-id');
        $blog->publishedAt = new \DateTimeImmutable('+1 day');

        $visibilityGuard = static::createStub(BlogVisibilityGuard::class);
        $visibilityGuard->method('isVisible')->willReturn(false);

        $reader = $this->reader(
            blogRepository: $this->blogRepositoryReturning(new BlogCollection([$blog])),
            settings: static::createStub(BlogSettings::class),
            visibilityGuard: $visibilityGuard,
        );

        $this->expectExceptionObject(BlogException::notFound('blog-id'));

        $reader->read('blog-id', new Request(), Generator::generateSalesChannelContext());
    }

    public function testASwitchedOffThreadAndATurnedOffTocCostNoExtraQuery(): void
    {
        $blog = $this->blog('blog-id');
        $blog->content = '<h2>Intro</h2><p>Body</p>';

        $visibilityGuard = static::createStub(BlogVisibilityGuard::class);
        $visibilityGuard->method('isVisible')->willReturn(true);
        $visibilityGuard->method('getVisibilityFilter')->willReturn(new EqualsFilter('publishedAt', null));

        $settings = static::createStub(BlogSettings::class);
        $settings->method('isTocEnabled')->willReturn(false);
        $settings->method('showAuthor')->willReturn(false);
        $settings->method('showReadingTime')->willReturn(false);
        $settings->method('areCommentsEnabled')->willReturn(false);
        $settings->method('autoPublishComments')->willReturn(true);
        $settings->method('getCommentsPerPage')->willReturn(7);
        $settings->method('getRelatedPostsCount')->willReturn(0);

        $commentRepository = $this->createMock(EntityRepository::class);
        $commentRepository->expects($this->never())->method('search');

        $mediaRepository = $this->createMock(EntityRepository::class);
        $mediaRepository->expects($this->never())->method('search');

        $estimator = static::createStub(BlogReadingTimeEstimator::class);
        $estimator->method('estimate')->willReturn(5);

        $reader = new BlogDetailReader(
            $this->blogRepositoryReturning(new BlogCollection([$blog])),
            $commentRepository,
            $mediaRepository,
            $visibilityGuard,
            $settings,
            new BlogCommentTree(),
            $estimator,
            $this->htmlSanitizer(),
        );

        $detail = $reader->read('blog-id', new Request(), Generator::generateSalesChannelContext());

        static::assertSame($blog, $detail->getBlog());
        static::assertNull($detail->getAuthor());
        static::assertNull($detail->getCoverMedia());
        static::assertNull($detail->getVideo());
        static::assertSame([], $detail->getToc());
        static::assertFalse($detail->getTocEnabled());
        static::assertSame('<h2 id="section-1">Intro</h2><p>Body</p>', $detail->getContent());
        static::assertSame(5, $detail->getReadingTimeMinutes());
        static::assertFalse($detail->getShowAuthor());
        static::assertFalse($detail->getShowReadingTime());

        $relatedBlogs = $detail->getRelatedBlogs();
        static::assertInstanceOf(BlogCollection::class, $relatedBlogs);
        static::assertCount(0, $relatedBlogs);

        static::assertFalse($detail->getCommentsEnabled());
        static::assertTrue($detail->getCommentsAutoPublish());
        static::assertSame(7, $detail->getCommentsPerPage());

        $comments = $detail->getComments();
        static::assertNotNull($comments);
        static::assertSame(0, $comments->getTotal());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testTheTocNumbersItsSectionsInDocumentOrderAndKeepsAnExistingAnchor(): void
    {
        $blog = $this->blog('blog-id');
        $blog->content = '<h2>Intro</h2><h3 class="lead">Details <strong>here</strong></h3><h2 id="custom">Kept</h2><h2> </h2>';

        $settings = static::createStub(BlogSettings::class);
        $settings->method('isTocEnabled')->willReturn(true);
        $settings->method('areCommentsEnabled')->willReturn(false);
        $settings->method('getRelatedPostsCount')->willReturn(0);

        $reader = $this->reader(
            blogRepository: $this->blogRepositoryReturning(new BlogCollection([$blog])),
            settings: $settings,
        );

        $detail = $reader->read('blog-id', new Request(), Generator::generateSalesChannelContext());

        static::assertSame([
            ['level' => 2, 'id' => 'section-1', 'title' => 'Intro'],
            ['level' => 3, 'id' => 'section-2', 'title' => 'Details here'],
            ['level' => 2, 'id' => 'section-3', 'title' => 'Kept'],
        ], $detail->getToc());

        static::assertStringContainsString('<h2 id="section-1">Intro</h2>', $detail->getContent());
        static::assertStringContainsString('<h3 class="lead" id="section-2">', $detail->getContent());
        static::assertStringContainsString('<h2 id="custom">Kept</h2>', $detail->getContent());
    }

    public function testTheThreadTheCoverAndTheRelatedEntriesAreReadInOnePass(): void
    {
        $blog = $this->blog('blog-id');
        $blog->content = 'Body';
        $blog->coverMediaId = 'media-id';
        $blog->author = $this->author('author-id');
        $blog->categories = ['assignment-id' => $this->assignment('assignment-id', 'category-id')];

        $related = $this->blog('related-id');
        $toppedUp = [$this->blog('top-up-1'), $this->blog('top-up-2')];

        $calls = 0;
        $blogRepository = $this->createMock(EntityRepository::class);
        $blogRepository->expects($this->exactly(3))
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria, $context) use (&$calls, $blog, $related, $toppedUp): EntitySearchResult {
                ++$calls;

                return match ($calls) {
                    1 => new EntitySearchResult(BlogEntity::ENTITY_NAME, 1, new BlogCollection([$blog]), null, $criteria, $context),
                    2 => new EntitySearchResult(BlogEntity::ENTITY_NAME, 1, new BlogCollection([$related]), null, $criteria, $context),
                    default => new EntitySearchResult(BlogEntity::ENTITY_NAME, 2, new BlogCollection($toppedUp), null, $criteria, $context),
                };
            });

        $comment = new BlogCommentEntity();
        $comment->id = 'comment-id';
        $comment->setUniqueIdentifier('comment-id');
        $comment->blogId = 'blog-id';
        $comment->content = 'A comment';
        $comment->status = true;
        $comment->setCreatedAt(new \DateTimeImmutable('2026-01-01 10:00:00'));

        $commentRepository = $this->createMock(EntityRepository::class);
        $commentRepository->expects($this->once())
            ->method('search')
            ->willReturnCallback(static fn (Criteria $criteria, $context): EntitySearchResult => new EntitySearchResult(
                BlogCommentEntity::ENTITY_NAME,
                1,
                new BlogCommentCollection([$comment]),
                null,
                $criteria,
                $context,
            ));

        $media = new MediaEntity();
        $media->setUniqueIdentifier('media-id');
        $mediaRepository = $this->createMock(EntityRepository::class);
        $mediaRepository->expects($this->once())
            ->method('search')
            ->with(static::callback(static fn (Criteria $criteria): bool => $criteria->getIds() === ['media-id']))
            ->willReturnCallback(static fn (Criteria $criteria, $context): EntitySearchResult => new EntitySearchResult(
                'media',
                1,
                new MediaCollection([$media]),
                null,
                $criteria,
                $context,
            ));

        $settings = static::createStub(BlogSettings::class);
        $settings->method('areCommentsEnabled')->willReturn(true);
        $settings->method('getCommentsPerPage')->willReturn(10);
        $settings->method('getRelatedPostsCount')->willReturn(3);

        $reader = new BlogDetailReader(
            $blogRepository,
            $commentRepository,
            $mediaRepository,
            $this->visibleGuard(),
            $settings,
            new BlogCommentTree(),
            static::createStub(BlogReadingTimeEstimator::class),
            $this->htmlSanitizer(),
        );

        $detail = $reader->read('blog-id', new Request(['comments' => '1']), Generator::generateSalesChannelContext());

        static::assertSame($media, $detail->getCoverMedia());
        static::assertInstanceOf(BlogAuthorEntity::class, $detail->getAuthor());

        $comments = $detail->getComments();
        static::assertNotNull($comments);
        static::assertSame(1, $comments->getTotal());

        $relatedBlogs = $detail->getRelatedBlogs();
        static::assertNotNull($relatedBlogs);
        static::assertCount(3, $relatedBlogs);
        static::assertSame(['related-id', 'top-up-1', 'top-up-2'], array_values($relatedBlogs->getIds()));
    }

    /**
     * @param EntityRepository<BlogCollection> $blogRepository
     */
    private function reader(
        EntityRepository $blogRepository,
        BlogSettings $settings,
        ?BlogVisibilityGuard $visibilityGuard = null,
    ): BlogDetailReader {
        return new BlogDetailReader(
            $blogRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $visibilityGuard ?? $this->visibleGuard(),
            $settings,
            new BlogCommentTree(),
            static::createStub(BlogReadingTimeEstimator::class),
            $this->htmlSanitizer(),
        );
    }

    private function visibleGuard(): BlogVisibilityGuard
    {
        $guard = static::createStub(BlogVisibilityGuard::class);
        $guard->method('isVisible')->willReturn(true);
        $guard->method('getVisibilityFilter')->willReturn(new EqualsFilter('publishedAt', null));

        return $guard;
    }

    /**
     * @return EntityRepository<BlogCollection>
     */
    private function blogRepositoryReturning(BlogCollection $blogs): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            static fn (Criteria $criteria, $context): EntitySearchResult => new EntitySearchResult(
                BlogEntity::ENTITY_NAME,
                $blogs->count(),
                $blogs,
                null,
                $criteria,
                $context,
            )
        );

        return $repository;
    }

    private function htmlSanitizer(): HtmlSanitizer
    {
        $sanitizer = static::createStub(HtmlSanitizer::class);
        $sanitizer->method('stripTags')->willReturnCallback(
            static fn (string $html): string => (string) preg_replace('/<[^>]*>/', '', $html)
        );

        return $sanitizer;
    }

    private function blog(string $id): BlogEntity
    {
        $blog = new BlogEntity();
        $blog->id = $id;
        $blog->setUniqueIdentifier($id);
        $blog->title = 'A headline';
        $blog->slug = 'a-headline';

        return $blog;
    }

    private function author(string $id): BlogAuthorEntity
    {
        $author = new BlogAuthorEntity();
        $author->setUniqueIdentifier($id);
        $author->name = 'Ada';

        return $author;
    }

    private function assignment(string $id, string $categoryId): BlogCategoryAssignmentEntity
    {
        $assignment = new BlogCategoryAssignmentEntity();
        $assignment->id = $id;
        $assignment->blogId = 'blog-id';
        $assignment->blogCategoryId = $categoryId;
        $assignment->setUniqueIdentifier($id);

        return $assignment;
    }
}
