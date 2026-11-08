<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Blog;

use Shopwell\Core\Content\Blog\Aggregate\BlogAuthor\BlogAuthorEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryCollection;
use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Content\Blog\SalesChannel\BlogListingStruct;
use Shopwell\Core\Content\Blog\Service\BlogDetailReader;
use Shopwell\Core\Content\Blog\Service\BlogListingReader;
use Shopwell\Core\Content\Blog\Service\BlogSettings;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopwell\Core\Framework\ContentSystem\SalesChannel\AbstractContentRoute;
use Shopwell\Core\Framework\ContentSystem\SalesChannel\ContentRouteResponse;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Controller\Exception\StorefrontException;
use Shopwell\Storefront\Page\MetaInformation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Turns what the readers found into the two pages the blog renders.
 *
 * The split is deliberate and one-directional: the readers own the queries and live in core, this
 * class owns the page object, the meta information and the page loaded event and lives in the
 * storefront. A core class that referenced `Shopwell\Storefront\Page\Page` would make the blog
 * unusable on an installation that runs headless, which is why none of the two exists on the other
 * side of that line.
 *
 * @final
 */
#[Package('discovery')]
class BlogPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly BlogListingReader $listingReader,
        private readonly BlogDetailReader $detailReader,
        private readonly BlogSettings $settings,
        private readonly AbstractContentRoute $contentRoute,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param string|null $categoryId the category of the request path, taken from the query when null
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext, ?string $categoryId = null): BlogPage
    {
        $pageNumber = max(1, (int) $request->query->get('p', '1'));
        $categoryId ??= $this->listingReader->categoryId($request);

        // the density of the listing page is the shop setting; which entries it shows is the reader's
        // answer, so the page and the content element cannot order them differently
        $listing = $this->listingReader->listing(
            BlogListingStruct::LAYOUT_CLASSIC,
            $categoryId,
            $pageNumber,
            $salesChannelContext
        );

        $categories = $this->listingReader->categoriesInUse($salesChannelContext->getContext());
        $contentLayoutId = $this->listingReader->layoutOf($categoryId, $categories);

        $page = new BlogPage();
        $page->setBlogs($listing->getBlogs() ?? new BlogCollection());
        $page->setCategories($categories);
        $page->setCoverMedia($listing->getCoverMedia() ?? new MediaCollection());
        $page->setReadingTimes($listing->getReadingTimes());
        $page->setActiveCategoryId($categoryId);
        $page->setContentLayoutId($contentLayoutId);
        $page->setContentPage($this->contentPage($contentLayoutId, $request, $salesChannelContext));
        $page->setLimit($this->settings->getPostsPerPage($salesChannelContext->getSalesChannelId()));
        $page->setPageNumber($listing->getPage());
        $page->setTotal($listing->getTotal());
        $page->setTotalPages($listing->getTotalPages());
        $page->setShowAuthor($listing->getShowAuthor());
        $page->setShowReadingTime($listing->getShowReadingTime());
        $page->setMetaInformation($this->listingMeta($categoryId, $categories));

        $this->eventDispatcher->dispatch(new BlogPageLoadedEvent($page, $salesChannelContext, $request));

        return $page;
    }

    /**
     * @throws BlogException when the entry does not exist or is not published yet
     */
    public function loadDetail(string $blogId, Request $request, SalesChannelContext $salesChannelContext): BlogDetailPage
    {
        $detail = $this->detailReader->read($blogId, $request, $salesChannelContext);
        $blog = $detail->getBlog();

        $page = new BlogDetailPage();

        if ($blog instanceof BlogEntity) {
            $page->setBlog($blog);
            $page->setContentPage($this->contentPage($blog->contentLayoutId, $request, $salesChannelContext));
        }

        $page->setAuthor($detail->getAuthor());
        $page->setCoverMedia($detail->getCoverMedia());
        $page->setVideo($detail->getVideo());
        $page->setToc($detail->getToc());
        $page->setContent($detail->getContent());
        $page->setTocEnabled($detail->getTocEnabled());
        $page->setReadingTimeMinutes($detail->getReadingTimeMinutes());
        $page->setShowAuthor($detail->getShowAuthor());
        $page->setShowReadingTime($detail->getShowReadingTime());
        $page->setRelatedBlogs($detail->getRelatedBlogs() ?? new BlogCollection());
        $page->setPreviousBlog($detail->getPreviousBlog());
        $page->setNextBlog($detail->getNextBlog());
        $page->setCommentsEnabled($detail->getCommentsEnabled());
        $page->setCommentsAutoPublish($detail->getCommentsAutoPublish());
        $page->setCommentsPerPage($detail->getCommentsPerPage());

        $comments = $detail->getComments();

        if ($comments !== null) {
            $page->setComments($comments);
        }

        $page->setMetaInformation($this->detailMeta($blog, $detail->getAuthor()));

        $this->eventDispatcher->dispatch(new BlogDetailPageLoadedEvent($page, $salesChannelContext, $request));

        return $page;
    }

    private function contentPage(
        ?string $layoutId,
        Request $request,
        SalesChannelContext $salesChannelContext,
    ): ?ContentPage {
        if ($layoutId === null) {
            return null;
        }

        $response = $this->contentRoute->load('/layout/' . $layoutId, $request, $salesChannelContext);
        if (!$response instanceof ContentRouteResponse) {
            throw StorefrontException::contentRequiresTwigOutput();
        }

        return $response->getContentPage();
    }

    /**
     * The title and description of a listing page.
     *
     * The seo block of the filtered category wins over its navigation label: a category is listed in
     * the chip bar with a short name, but its listing page is a landing page and needs a title of its
     * own. Without a filter the listing is the blog itself and falls back to its name.
     */
    private function listingMeta(?string $categoryId, BlogCategoryCollection $categories): MetaInformation
    {
        $meta = new MetaInformation();

        if ($categoryId !== null) {
            foreach ($categories as $category) {
                if ($category->id !== $categoryId) {
                    continue;
                }

                $meta->setMetaTitle($category->metaTitle ?? $category->name);
                $meta->setMetaDescription($category->metaDescription ?? $category->description ?? '');
                $meta->setMetaKeywords($category->keywords ?? '');

                return $meta;
            }
        }

        $meta->setMetaTitle('Blog');

        return $meta;
    }

    private function detailMeta(?BlogEntity $blog, ?BlogAuthorEntity $author): MetaInformation
    {
        $meta = new MetaInformation();

        if ($blog !== null) {
            $meta->setMetaTitle($blog->metaTitle ?? $blog->title);
            $meta->setMetaDescription($blog->metaDescription ?? $blog->teaser ?? '');
            $meta->setMetaKeywords($blog->keywords ?? '');
        }

        if ($author !== null) {
            $meta->setAuthor($author->name);
        }

        return $meta;
    }
}
