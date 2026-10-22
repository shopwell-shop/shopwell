<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Service;

use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryCollection;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategoryAssignment\BlogCategoryAssignmentCollection;
use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Blog\SalesChannel\BlogListingStruct;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reads a page of the blog listing.
 *
 * This is the query layer and nothing more: no page object, no meta information, no event. The
 * storefront page loader wraps the result into a page, the `blog-listing` cms element renders the
 * struct directly, and both therefore answer "which entries are published, in which order, with which
 * cover and which reading time" from the same code. A second implementation of that question is how
 * two listings of the same shop end up sorted differently.
 *
 * Visibility is not decided here either: the listing asks {@see BlogVisibilityGuard::getVisibilityFilter()}
 * for the filter, so a change of the rule cannot leave one of the two renderers behind.
 *
 * Nothing in this class pins a language. Every query runs with the context it was handed, which means
 * the dal resolves `title`, `slug`, `teaser` from `blog_translation` in the language of the request and
 * falls back to the parent language where a translation is missing. The category chips travel through
 * the same path, so a shop that writes in two languages does not show german chip labels under a
 * chinese article.
 *
 * @final
 */
#[Package('discovery')]
class BlogListingReader
{
    /**
     * The upper bound of entries the chip bar looks at. A blog with more published entries than this
     * still shows chips, it only stops counting precisely.
     */
    private const CATEGORY_SAMPLE_SIZE = 1000;

    /**
     * @internal
     *
     * @param EntityRepository<BlogCollection> $blogRepository
     * @param EntityRepository<BlogCategoryCollection> $categoryRepository
     * @param EntityRepository<BlogCategoryAssignmentCollection> $assignmentRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(
        private readonly EntityRepository $blogRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $assignmentRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly BlogVisibilityGuard $visibilityGuard,
        private readonly BlogSettings $settings,
        private readonly BlogReadingTimeEstimator $readingTimeEstimator,
    ) {
    }

    /**
     * A window of entries with its own density and its own page number.
     *
     * @param string|null $categoryId the category to filter by, null for all entries
     * @param int $page one based page number, values below one are clamped
     * @param string $layout one of BlogListingStruct::LAYOUTS, unknown values fall back to classic
     */
    public function listing(
        string $layout,
        ?string $categoryId,
        int $page,
        SalesChannelContext $salesChannelContext,
    ): BlogListingStruct {
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $context = $salesChannelContext->getContext();
        $limit = $this->settings->getPostsPerPage($salesChannelId);
        $pageNumber = max(1, $page);

        $criteria = new Criteria();
        $criteria->addFilter($this->visibilityGuard->getVisibilityFilter());
        $criteria->addAssociation('author');
        $criteria->addAssociation('categories.blogCategory');
        $criteria->addSorting(
            new FieldSorting('publishedAt', FieldSorting::DESCENDING),
            new FieldSorting('createdAt', FieldSorting::DESCENDING)
        );
        $criteria->setLimit($limit);
        $criteria->setOffset(($pageNumber - 1) * $limit);

        if ($categoryId !== null) {
            $criteria->addFilter(new EqualsFilter('categories.blogCategoryId', $categoryId));
        }

        $result = $this->blogRepository->search($criteria, $context);

        /** @var BlogCollection $blogs */
        $blogs = $result->getEntities();

        $struct = new BlogListingStruct();
        $struct->setBlogs($blogs);
        $struct->setCoverMedia($this->coverMediaOf($blogs, $context));
        $struct->setReadingTimes($this->readingTimesOf($blogs));
        $struct->setLayout($layout);
        $struct->setPage($pageNumber);
        $struct->setTotal($result->getTotal());
        $struct->setTotalPages($limit > 0 ? (int) ceil($result->getTotal() / $limit) : 0);
        $struct->setShowAuthor($this->settings->showAuthor($salesChannelId));
        $struct->setShowReadingTime($this->settings->showReadingTime($salesChannelId));

        return $struct;
    }

    /**
     * The categories that hold at least one entry a visitor may see, the heaviest first.
     *
     * The chip bar must not offer a category that leads to an empty listing, so the set is derived
     * from the visible entries and not from the category table. The names come out of the translation
     * of the current language, because the query carries the request context and not a fixed one.
     */
    public function categoriesInUse(Context $context): BlogCategoryCollection
    {
        $visible = $this->blogRepository->searchIds(
            (new Criteria())
                ->addFilter($this->visibilityGuard->getVisibilityFilter())
                ->setLimit(self::CATEGORY_SAMPLE_SIZE),
            $context
        );

        $blogIds = array_values($visible->getIds());

        if ($blogIds === []) {
            return new BlogCategoryCollection();
        }

        $assignments = $this->assignmentRepository->search(
            (new Criteria())->addFilter(new EqualsAnyFilter('blogId', $blogIds)),
            $context
        )->getEntities();

        $counts = [];

        foreach ($assignments as $assignment) {
            $counts[$assignment->blogCategoryId] = ($counts[$assignment->blogCategoryId] ?? 0) + 1;
        }

        if ($counts === []) {
            return new BlogCategoryCollection();
        }

        $categories = $this->categoryRepository->search(
            (new Criteria(array_keys($counts)))->addFilter(new EqualsFilter('active', true)),
            $context
        )->getEntities();

        $sorted = $categories->getElements();

        usort($sorted, static fn (BlogCategoryEntity $a, BlogCategoryEntity $b): int => ($counts[$b->id] ?? 0) <=> ($counts[$a->id] ?? 0));

        return new BlogCategoryCollection($sorted);
    }

    /**
     * The cms layout of the listing the visitor filtered for, null when there is none.
     *
     * Resolved from the loaded categories instead of a second query: the chip bar already reads the
     * filtered category, and a page that renders its own listing must not cost an extra round trip.
     *
     * @param BlogCategoryCollection $categories the categories of {@see self::categoriesInUse()}
     */
    public function layoutOf(?string $categoryId, BlogCategoryCollection $categories): ?string
    {
        if ($categoryId === null) {
            return null;
        }

        foreach ($categories as $category) {
            if ($category->id === $categoryId) {
                return $category->cmsPageId;
            }
        }

        return null;
    }

    /**
     * The category of the request path, null when the request names none.
     *
     * A query parameter and not a path segment: `/blog?category=…` is the filter the chip bar produces
     * without javascript. The readable url of a category is a route of its own and hands the id in
     * directly, so the caller passes that one and never reaches this method.
     */
    public function categoryId(Request $request): ?string
    {
        $value = $request->query->get('category');

        if (!\is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * The cover images of a whole page in one query - one query per card would be a listing of twelve
     * round trips.
     */
    public function coverMediaOf(BlogCollection $blogs, Context $context): MediaCollection
    {
        $ids = [];

        foreach ($blogs as $blog) {
            if ($blog->coverMediaId !== null) {
                $ids[] = $blog->coverMediaId;
            }
        }

        if ($ids === []) {
            return new MediaCollection();
        }

        return $this->mediaRepository->search(new Criteria(array_values(array_unique($ids))), $context)->getEntities();
    }

    /**
     * @return array<string, int> entry id => minutes
     */
    public function readingTimesOf(BlogCollection $blogs): array
    {
        $times = [];

        foreach ($blogs as $blog) {
            $times[$blog->id] = $this->readingTimeEstimator->estimate($blog->content ?? '');
        }

        return $times;
    }
}
