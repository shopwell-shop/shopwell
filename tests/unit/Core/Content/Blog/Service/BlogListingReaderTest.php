<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryCollection;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategoryAssignment\BlogCategoryAssignmentCollection;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategoryAssignment\BlogCategoryAssignmentEntity;
use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\Service\BlogListingReader;
use Shopwell\Core\Content\Blog\Service\BlogReadingTimeEstimator;
use Shopwell\Core\Content\Blog\Service\BlogSettings;
use Shopwell\Core\Content\Blog\Service\BlogVisibilityGuard;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * The reader is the single query layer for both listings - the page and the cms element - so the
 * window it asks for and the filter it applies are the whole behaviour. The chip bar is the part that
 * is easy to get wrong: it has to be derived from visible entries, ordered by how many entries it
 * holds, and it must not offer a category that would open an empty listing.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogListingReader::class)]
class BlogListingReaderTest extends TestCase
{
    public function testTheRequestedPageIsTurnedIntoALimitAndAnOffset(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $context = $salesChannelContext->getContext();

        $blogs = new BlogCollection([$this->blog('a'), $this->blog('b')]);
        $captured = null;

        $blogRepository = $this->createMock(EntityRepository::class);
        $blogRepository->expects($this->once())
            ->method('search')
            ->willReturnCallback(static function (Criteria $criteria, $searchContext) use (&$captured, $blogs, $context): EntitySearchResult {
                $captured = $criteria;

                return new EntitySearchResult(BlogEntity::ENTITY_NAME, 25, $blogs, null, $criteria, $context);
            });

        $settings = $this->createMock(BlogSettings::class);
        $settings->expects($this->once())
            ->method('getPostsPerPage')
            ->with($salesChannelContext->getSalesChannelId())
            ->willReturn(12);
        $settings->method('showAuthor')->willReturn(true);
        $settings->method('showReadingTime')->willReturn(false);

        $reader = new BlogListingReader(
            $blogRepository,
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(BlogVisibilityGuard::class),
            $settings,
            static::createStub(BlogReadingTimeEstimator::class),
        );

        $listing = $reader->listing('grid', null, 3, $salesChannelContext);

        static::assertSame('grid', $listing->getLayout());
        static::assertSame(3, $listing->getPage());
        static::assertSame(25, $listing->getTotal());
        static::assertSame(3, $listing->getTotalPages());
        static::assertTrue($listing->getShowAuthor());
        static::assertFalse($listing->getShowReadingTime());

        static::assertInstanceOf(Criteria::class, $captured);
        static::assertSame(12, $captured->getLimit());
        static::assertSame(24, $captured->getOffset());
        static::assertTrue($captured->hasAssociation('author'));
        static::assertTrue($captured->hasAssociation('categories'));
        static::assertTrue($captured->getAssociation('categories')->hasAssociation('blogCategory'));

        $sorting = $captured->getSorting();
        static::assertSame('publishedAt', $sorting[0]->getField());
        static::assertSame('createdAt', $sorting[1]->getField());
    }

    public function testAPageNumberBelowOneIsClampedToTheFirstPage(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $captured = null;

        $blogRepository = static::createStub(EntityRepository::class);
        $blogRepository->method('search')->willReturnCallback(
            static function (Criteria $criteria, $context) use (&$captured): EntitySearchResult {
                $captured = $criteria;

                return new EntitySearchResult(BlogEntity::ENTITY_NAME, 0, new BlogCollection(), null, $criteria, $context);
            }
        );

        $settings = static::createStub(BlogSettings::class);
        $settings->method('getPostsPerPage')->willReturn(12);

        $reader = $this->reader($blogRepository, $settings);

        $listing = $reader->listing('classic', null, -5, $salesChannelContext);

        static::assertSame(1, $listing->getPage());

        static::assertInstanceOf(Criteria::class, $captured);
        static::assertSame(0, $captured->getOffset());
        static::assertSame(0, $listing->getTotalPages());
    }

    public function testAFilteredListingAddsTheCategoryToTheSameQuery(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $captured = null;

        $blogRepository = static::createStub(EntityRepository::class);
        $blogRepository->method('search')->willReturnCallback(
            static function (Criteria $criteria, $context) use (&$captured): EntitySearchResult {
                $captured = $criteria;

                return new EntitySearchResult(BlogEntity::ENTITY_NAME, 0, new BlogCollection(), null, $criteria, $context);
            }
        );

        $settings = static::createStub(BlogSettings::class);
        $settings->method('getPostsPerPage')->willReturn(12);

        $this->reader($blogRepository, $settings)->listing('classic', 'category-id', 1, $salesChannelContext);

        static::assertInstanceOf(Criteria::class, $captured);

        $filters = array_values(array_filter(
            $captured->getFilters(),
            static fn ($filter): bool => $filter instanceof EqualsFilter && $filter->getField() === 'categories.blogCategoryId'
        ));

        static::assertCount(1, $filters);
        static::assertSame('category-id', $filters[0]->getValue());
    }

    public function testTheChipBarIsDerivedFromVisibleEntriesAndOrderedByTheirCount(): void
    {
        $context = Generator::generateSalesChannelContext()->getContext();

        $blogRepository = $this->createMock(EntityRepository::class);
        $blogRepository->expects($this->once())
            ->method('searchIds')
            ->willReturn(IdSearchResult::fromIds(['blog-1', 'blog-2', 'blog-3'], new Criteria(), $context));

        $assignments = new BlogCategoryAssignmentCollection([
            $this->assignment('a-1', 'category-a'),
            $this->assignment('a-2', 'category-a'),
            $this->assignment('a-3', 'category-b'),
        ]);

        $assignmentRepository = $this->createMock(EntityRepository::class);
        $assignmentRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                BlogCategoryAssignmentEntity::ENTITY_NAME,
                3,
                $assignments,
                null,
                new Criteria(),
                $context,
            ));

        $categoryA = $this->category('category-a', 'Guides');
        $categoryB = $this->category('category-b', 'News');

        $categoryRepository = $this->createMock(EntityRepository::class);
        $categoryRepository->expects($this->once())
            ->method('search')
            ->with(static::callback(static function (Criteria $criteria): bool {
                $filters = $criteria->getFilters();

                return $criteria->getIds() === ['category-a', 'category-b']
                    && $filters[0] instanceof EqualsFilter
                    && $filters[0]->getField() === 'active'
                    && $filters[0]->getValue() === true;
            }))
            ->willReturn(new EntitySearchResult(
                BlogCategoryEntity::ENTITY_NAME,
                2,
                new BlogCategoryCollection([$categoryB, $categoryA]),
                null,
                new Criteria(),
                $context,
            ));

        $reader = new BlogListingReader(
            $blogRepository,
            $categoryRepository,
            $assignmentRepository,
            static::createStub(EntityRepository::class),
            static::createStub(BlogVisibilityGuard::class),
            static::createStub(BlogSettings::class),
            static::createStub(BlogReadingTimeEstimator::class),
        );

        $categories = $reader->categoriesInUse($context);

        static::assertCount(2, $categories);

        $first = $categories->first();
        static::assertNotNull($first);
        static::assertSame('category-a', $first->id);

        $last = $categories->last();
        static::assertNotNull($last);
        static::assertSame('category-b', $last->id);
    }

    public function testTheChipBarSkipsTheQueryWhenNoEntryIsVisible(): void
    {
        $context = Generator::generateSalesChannelContext()->getContext();

        $blogRepository = static::createStub(EntityRepository::class);
        $blogRepository->method('searchIds')
            ->willReturn(IdSearchResult::fromIds([], new Criteria(), $context));

        $assignmentRepository = $this->createMock(EntityRepository::class);
        $assignmentRepository->expects($this->never())->method('search');

        $reader = new BlogListingReader(
            $blogRepository,
            static::createStub(EntityRepository::class),
            $assignmentRepository,
            static::createStub(EntityRepository::class),
            static::createStub(BlogVisibilityGuard::class),
            static::createStub(BlogSettings::class),
            static::createStub(BlogReadingTimeEstimator::class),
        );

        static::assertCount(0, $reader->categoriesInUse($context));
    }

    public function testTheLayoutComesFromTheFilteredCategoryOnly(): void
    {
        $category = $this->category('category-a', 'Guides');
        $category->cmsPageId = 'cms-page-id';

        $categories = new BlogCategoryCollection([$category]);

        $reader = $this->reader(static::createStub(EntityRepository::class), static::createStub(BlogSettings::class));

        static::assertNull($reader->layoutOf(null, $categories));
        static::assertNull($reader->layoutOf('unknown', $categories));
        static::assertSame('cms-page-id', $reader->layoutOf('category-a', $categories));
    }

    public function testTheCategoryOfTheRequestIsReadFromTheQueryAndNormalised(): void
    {
        $reader = $this->reader(static::createStub(EntityRepository::class), static::createStub(BlogSettings::class));

        static::assertNull($reader->categoryId(new Request()));
        static::assertNull($reader->categoryId(new Request(['category' => '   '])));
        static::assertSame('category-a', $reader->categoryId(new Request(['category' => '  category-a '])));
    }

    public function testOneQueryLoadsTheCoversOfTheWholePageAndDuplicatesAreDropped(): void
    {
        $context = Generator::generateSalesChannelContext()->getContext();

        $first = $this->blog('a');
        $first->coverMediaId = 'media-a';
        $second = $this->blog('b');
        $second->coverMediaId = 'media-a';
        $withoutCover = $this->blog('c');

        $mediaRepository = $this->createMock(EntityRepository::class);
        $mediaRepository->expects($this->once())
            ->method('search')
            ->with(static::callback(static fn (Criteria $criteria): bool => $criteria->getIds() === ['media-a']))
            ->willReturn(new EntitySearchResult(
                'media',
                1,
                new MediaCollection([$this->media('media-a')]),
                null,
                new Criteria(),
                $context,
            ));

        $reader = new BlogListingReader(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            $mediaRepository,
            static::createStub(BlogVisibilityGuard::class),
            static::createStub(BlogSettings::class),
            static::createStub(BlogReadingTimeEstimator::class),
        );

        $media = $reader->coverMediaOf(new BlogCollection([$first, $second, $withoutCover]), $context);

        static::assertCount(1, $media);
    }

    public function testReadingTimesAreKeyedByEntryIdAndComputedFromTheBody(): void
    {
        $estimator = $this->createMock(BlogReadingTimeEstimator::class);
        $estimator->expects($this->exactly(2))
            ->method('estimate')
            ->willReturnCallback(static fn (string $content): int => $content === 'short' ? 1 : 9);

        $reader = new BlogListingReader(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(BlogVisibilityGuard::class),
            static::createStub(BlogSettings::class),
            $estimator,
        );

        $short = $this->blog('short-entry');
        $short->content = 'short';
        $long = $this->blog('long-entry');
        $long->content = 'long';

        static::assertSame(
            ['short-entry' => 1, 'long-entry' => 9],
            $reader->readingTimesOf(new BlogCollection([$short, $long]))
        );
    }

    /**
     * @param EntityRepository<BlogCollection> $blogRepository
     */
    private function reader(EntityRepository $blogRepository, BlogSettings $settings): BlogListingReader
    {
        return new BlogListingReader(
            $blogRepository,
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(BlogVisibilityGuard::class),
            $settings,
            static::createStub(BlogReadingTimeEstimator::class),
        );
    }

    private function blog(string $id): BlogEntity
    {
        $blog = new BlogEntity();
        $blog->id = $id;
        $blog->setUniqueIdentifier($id);

        return $blog;
    }

    private function category(string $id, string $name): BlogCategoryEntity
    {
        $category = new BlogCategoryEntity();
        $category->id = $id;
        $category->name = $name;
        $category->setUniqueIdentifier($id);

        return $category;
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

    private function media(string $id): MediaEntity
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier($id);

        return $media;
    }
}
