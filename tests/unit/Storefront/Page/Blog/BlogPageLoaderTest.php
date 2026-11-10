<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Blog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogAuthor\BlogAuthorEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryCollection;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogSpecificationSource;
use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Content\Blog\SalesChannel\BlogListingStruct;
use Shopwell\Core\Content\Blog\Service\BlogDetailReader;
use Shopwell\Core\Content\Blog\Service\BlogDetailStruct;
use Shopwell\Core\Content\Blog\Service\BlogListingReader;
use Shopwell\Core\Content\Blog\Service\BlogSettings;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\LayoutReference;
use Shopwell\Core\Framework\ContentSystem\Output\RenderResult;
use Shopwell\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopwell\Core\Framework\ContentSystem\SalesChannel\AbstractContentRoute;
use Shopwell\Core\Framework\ContentSystem\SalesChannel\ContentRouteResponse;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopwell\Storefront\Page\Blog\BlogDetailPageLoadedEvent;
use Shopwell\Storefront\Page\Blog\BlogPageLoadedEvent;
use Shopwell\Storefront\Page\Blog\BlogPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The loader is the seam between the core readers and the storefront page: it must not run a query
 * of its own, it must turn a missing content layout into null instead of an error, and it must dispatch
 * the page loaded event exactly once with the page it is about to return. The meta information is
 * the other visible contract - a category listing is a landing page and needs the seo block of that
 * category, not the label of the chip bar.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogPageLoader::class)]
class BlogPageLoaderTest extends TestCase
{
    public function testTheListingPageIsAssembledFromTheReaderAndTheShopSetting(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $request = new Request(['p' => '2']);

        $blogs = new BlogCollection([$this->blog('blog-1'), $this->blog('blog-2')]);

        $listing = new BlogListingStruct();
        $listing->setBlogs($blogs);
        $listing->setCoverMedia(new MediaCollection());
        $listing->setReadingTimes(['blog-1' => 2, 'blog-2' => 5]);
        $listing->setPage(2);
        $listing->setTotal(20);
        $listing->setTotalPages(2);
        $listing->setShowAuthor(false);
        $listing->setShowReadingTime(true);

        $category = new BlogCategoryEntity();
        $category->id = 'category-id';
        $category->name = 'Guides';
        $category->slug = 'guides';
        $category->metaTitle = 'Guides — the blog';
        $category->metaDescription = 'All guides';
        $category->keywords = 'guides, howto';
        $category->contentLayoutId = 'content-layout-id';
        $category->setUniqueIdentifier('category-id');

        $listingReader = $this->createMock(BlogListingReader::class);
        $listingReader->expects($this->once())
            ->method('listing')
            ->with(BlogListingStruct::LAYOUT_CLASSIC, 'category-id', 2, $salesChannelContext)
            ->willReturn($listing);
        $listingReader->expects($this->never())->method('categoryId');
        $listingReader->method('categoriesInUse')->willReturn(new BlogCategoryCollection([$category]));
        $listingReader->expects($this->once())
            ->method('layoutOf')
            ->with('category-id', static::isInstanceOf(BlogCategoryCollection::class))
            ->willReturn('content-layout-id');

        $settings = static::createStub(BlogSettings::class);
        $settings->method('getPostsPerPage')->willReturn(12);

        $contentPage = ContentPage::fromRenderResult(new RenderResult([], LayoutReference::create('content-layout-id', 'Blog layout', null), null));

        $loader = new BlogPageLoader(
            $listingReader,
            static::createStub(BlogDetailReader::class),
            $settings,
            $this->contentRoute($contentPage),
            $this->eventDispatcher(BlogPageLoadedEvent::class),
        );

        $page = $loader->load($request, $salesChannelContext, 'category-id');

        static::assertSame($blogs, $page->getBlogs());
        $categories = $page->getCategories();
        static::assertNotNull($categories);
        static::assertSame([$category], array_values($categories->getElements()));
        static::assertSame(['blog-1' => 2, 'blog-2' => 5], $page->getReadingTimes());
        static::assertSame('category-id', $page->getActiveCategoryId());
        static::assertSame('content-layout-id', $page->getContentLayoutId());
        static::assertEquals($contentPage, $page->getContentPage());
        static::assertSame(12, $page->getLimit());
        static::assertSame(2, $page->getPageNumber());
        static::assertSame(20, $page->getTotal());
        static::assertSame(2, $page->getTotalPages());
        static::assertFalse($page->getShowAuthor());

        $meta = $page->getMetaInformation();
        static::assertNotNull($meta);
        static::assertSame('Guides — the blog', $meta->getMetaTitle());
        static::assertSame('All guides', $meta->getMetaDescription());
        static::assertSame('guides, howto', $meta->getMetaKeywords());
    }

    public function testTheUnfilteredListingNeedsNoContentLayoutAndFallsBackToTheBlogTitle(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $request = new Request();

        $listing = new BlogListingStruct();
        $listing->setBlogs(new BlogCollection());

        $listingReader = $this->createMock(BlogListingReader::class);
        $listingReader->expects($this->once())
            ->method('listing')
            ->with(BlogListingStruct::LAYOUT_CLASSIC, null, 1, $salesChannelContext)
            ->willReturn($listing);
        $listingReader->method('categoryId')->willReturn(null);
        $listingReader->method('categoriesInUse')->willReturn(new BlogCategoryCollection());
        $listingReader->method('layoutOf')->willReturn(null);

        $contentRoute = $this->createMock(AbstractContentRoute::class);
        $contentRoute->expects($this->never())->method('load');

        $loader = new BlogPageLoader(
            $listingReader,
            static::createStub(BlogDetailReader::class),
            static::createStub(BlogSettings::class),
            $contentRoute,
            $this->eventDispatcher(BlogPageLoadedEvent::class),
        );

        $page = $loader->load($request, $salesChannelContext);

        static::assertNull($page->getActiveCategoryId());
        static::assertNull($page->getContentLayoutId());
        static::assertNull($page->getContentPage());
        $meta = $page->getMetaInformation();
        static::assertNotNull($meta);
        static::assertSame('Blog', $meta->getMetaTitle());
        static::assertSame('', $meta->getMetaDescription());
    }

    public function testTheDetailPageCarriesTheArticleTheMetaAndTheResolvedLayout(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $request = new Request();

        $blog = $this->blog('blog-id');
        $blog->title = 'A headline';
        $blog->teaser = 'A teaser';
        $blog->metaTitle = 'A seo headline';
        $blog->keywords = 'headline, blog';
        $blog->contentLayoutId = 'content-layout-id';

        $author = new BlogAuthorEntity();
        $author->setUniqueIdentifier('author-id');
        $author->name = 'Ada';

        $detail = new BlogDetailStruct();
        $detail->setBlog($blog);
        $detail->setAuthor($author);
        $detail->setToc([['level' => 2, 'id' => 'section-1', 'title' => 'A headline']]);
        $detail->setContent('<h2 id="section-1">A headline</h2>');
        $detail->setReadingTimeMinutes(3);
        $detail->setCommentsEnabled(false);
        $detail->setCommentsPerPage(5);
        $detail->setRelatedBlogs(new BlogCollection());

        $detailReader = $this->createMock(BlogDetailReader::class);
        $detailReader->expects($this->once())
            ->method('read')
            ->with('blog-id', $request, $salesChannelContext)
            ->willReturn($detail);

        $contentPage = ContentPage::fromRenderResult(new RenderResult([], LayoutReference::create('content-layout-id', 'Blog layout', null), null));

        $loader = new BlogPageLoader(
            static::createStub(BlogListingReader::class),
            $detailReader,
            static::createStub(BlogSettings::class),
            $this->contentRoute($contentPage),
            $this->eventDispatcher(BlogDetailPageLoadedEvent::class),
        );

        $page = $loader->loadDetail('blog-id', $request, $salesChannelContext);

        static::assertSame($blog, $page->getBlog());
        static::assertSame($author, $page->getAuthor());
        static::assertEquals($contentPage, $page->getContentPage());
        static::assertSame([['level' => 2, 'id' => 'section-1', 'title' => 'A headline']], $page->getToc());
        static::assertSame('<h2 id="section-1">A headline</h2>', $page->getContent());
        static::assertSame(3, $page->getReadingTimeMinutes());
        static::assertFalse($page->getCommentsEnabled());
        static::assertSame(5, $page->getCommentsPerPage());

        $meta = $page->getMetaInformation();
        static::assertNotNull($meta);
        static::assertSame('A seo headline', $meta->getMetaTitle());
        static::assertSame('A teaser', $meta->getMetaDescription());
        static::assertSame('headline, blog', $meta->getMetaKeywords());
        static::assertSame('Ada', $meta->getAuthor());
    }

    public function testTheDetailMetaFallsBackToTheTitleAndTheTeaser(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $blog = $this->blog('blog-id');
        $blog->title = 'A headline';
        $blog->teaser = 'A teaser';

        $detail = new BlogDetailStruct();
        $detail->setBlog($blog);

        $detailReader = static::createStub(BlogDetailReader::class);
        $detailReader->method('read')->willReturn($detail);

        $loader = new BlogPageLoader(
            static::createStub(BlogListingReader::class),
            $detailReader,
            static::createStub(BlogSettings::class),
            static::createStub(AbstractContentRoute::class),
            $this->eventDispatcher(BlogDetailPageLoadedEvent::class),
        );

        $page = $loader->loadDetail('blog-id', new Request(), $salesChannelContext);

        $meta = $page->getMetaInformation();
        static::assertNotNull($meta);
        static::assertSame('A headline', $meta->getMetaTitle());
        static::assertSame('A teaser', $meta->getMetaDescription());
    }

    public function testAnUnreadableEntryPropagatesInsteadOfRenderingAnEmptyPage(): void
    {
        $detailReader = static::createStub(BlogDetailReader::class);
        $detailReader->method('read')->willThrowException(BlogException::notFound('missing'));

        $loader = new BlogPageLoader(
            static::createStub(BlogListingReader::class),
            $detailReader,
            static::createStub(BlogSettings::class),
            static::createStub(AbstractContentRoute::class),
            static::createStub(EventDispatcherInterface::class),
        );

        $this->expectExceptionObject(BlogException::notFound('missing'));

        $loader->loadDetail('missing', new Request(), Generator::generateSalesChannelContext());
    }

    public function testBoundAssignmentWinsOverLegacyBodyLayout(): void
    {
        $blog = $this->blog('blog-id');
        $blog->contentLayoutId = 'legacy-layout';
        $detail = new BlogDetailStruct();
        $detail->setBlog($blog);
        $reader = static::createStub(BlogDetailReader::class);
        $reader->method('read')->willReturn($detail);
        $source = static::createStub(BlogSpecificationSource::class);
        $source->method('findAssignedLayoutId')->willReturn('assigned-layout');
        $route = $this->createMock(AbstractContentRoute::class);
        $route->expects($this->once())->method('load')->with('/blog/blog-id', static::isInstanceOf(Request::class), static::anything())
            ->willReturn(new ContentRouteResponse(new RenderResult([], LayoutReference::create('assigned-layout', 'Assigned layout', null), null)));
        $loader = new BlogPageLoader(static::createStub(BlogListingReader::class), $reader, static::createStub(BlogSettings::class), $route, static::createStub(EventDispatcherInterface::class), $source);

        $page = $loader->loadDetail('blog-id', new Request(), Generator::generateSalesChannelContext());

        static::assertTrue($page->isEntityBoundLayout());
        static::assertSame('assigned-layout', $page->getContentPage()?->id);
    }

    public function testLegacyBodyLayoutWinsOverSharedDefaultWhenUnassigned(): void
    {
        $blog = $this->blog('blog-id');
        $blog->contentLayoutId = 'legacy-layout';
        $detail = new BlogDetailStruct();
        $detail->setBlog($blog);
        $reader = static::createStub(BlogDetailReader::class);
        $reader->method('read')->willReturn($detail);
        $source = static::createStub(BlogSpecificationSource::class);
        $source->method('findAssignedLayoutId')->willReturn(null);
        $route = $this->createMock(AbstractContentRoute::class);
        $route->expects($this->once())->method('load')->with('/layout/legacy-layout', static::anything(), static::anything())
            ->willReturn(new ContentRouteResponse(new RenderResult([], LayoutReference::create('legacy-layout', 'Legacy', null), null)));
        $loader = new BlogPageLoader(static::createStub(BlogListingReader::class), $reader, static::createStub(BlogSettings::class), $route, static::createStub(EventDispatcherInterface::class), $source);

        $page = $loader->loadDetail('blog-id', new Request(), Generator::generateSalesChannelContext());

        static::assertFalse($page->isEntityBoundLayout());
        static::assertSame('legacy-layout', $page->getContentPage()?->id);
    }

    public function testMissingBoundDefaultFallsBackToThemeTemplate(): void
    {
        $blog = $this->blog('blog-id');
        $detail = new BlogDetailStruct();
        $detail->setBlog($blog);
        $reader = static::createStub(BlogDetailReader::class);
        $reader->method('read')->willReturn($detail);
        $route = static::createStub(AbstractContentRoute::class);
        $route->method('load')->willThrowException(ContentSystemException::layoutAssignmentNotFound('blog', 'blog-id', 'channel'));
        $loader = new BlogPageLoader(static::createStub(BlogListingReader::class), $reader, static::createStub(BlogSettings::class), $route, static::createStub(EventDispatcherInterface::class), static::createStub(BlogSpecificationSource::class));

        $page = $loader->loadDetail('blog-id', new Request(), Generator::generateSalesChannelContext());

        static::assertNull($page->getContentPage());
        static::assertFalse($page->isEntityBoundLayout());
    }

    public function testHomeUsesChannelListingDefaultAndPreservesRequest(): void
    {
        $context = Generator::generateSalesChannelContext();
        $config = new StaticSystemConfigService();
        $config->set('core.content_system.default_blog_listing_content_layout', 'global-listing');
        $config->set('core.content_system.default_blog_listing_content_layout', 'channel-listing', $context->getSalesChannelId());
        $reader = static::createStub(BlogListingReader::class);
        $reader->method('listing')->willReturn(new BlogListingStruct());
        $reader->method('categoriesInUse')->willReturn(new BlogCategoryCollection());
        $request = new Request(['categoryId' => 'untrusted', 'p' => 2]);
        $route = $this->createMock(AbstractContentRoute::class);
        $route->expects($this->once())->method('load')->with('/layout/channel-listing', static::callback(static fn (Request $request): bool => $request->query->get('categoryId') === '' && $request->query->getInt('p') === 2), $context)
            ->willReturn(new ContentRouteResponse(new RenderResult([], LayoutReference::create('channel-listing', 'Listing', null), null)));
        $loader = new BlogPageLoader($reader, static::createStub(BlogDetailReader::class), static::createStub(BlogSettings::class), $route, static::createStub(EventDispatcherInterface::class), systemConfig: $config);

        $page = $loader->load($request, $context);

        static::assertSame('channel-listing', $page->getContentLayoutId());
        static::assertSame('untrusted', $request->query->get('categoryId'));
    }

    public function testCategoryAssignmentWinsOverDefaultAndSuppliesRouteCategory(): void
    {
        $context = Generator::generateSalesChannelContext();
        $config = new StaticSystemConfigService();
        $config->set('core.content_system.default_blog_listing_content_layout', 'default-listing');
        $reader = static::createStub(BlogListingReader::class);
        $reader->method('listing')->willReturn(new BlogListingStruct());
        $reader->method('categoriesInUse')->willReturn(new BlogCategoryCollection());
        $reader->method('layoutOf')->willReturn('category-listing');
        $route = $this->createMock(AbstractContentRoute::class);
        $route->expects($this->once())->method('load')->with('/layout/category-listing', static::callback(static fn (Request $request): bool => $request->query->get('categoryId') === 'route-category'), $context)
            ->willReturn(new ContentRouteResponse(new RenderResult([], LayoutReference::create('category-listing', 'Category', null), null)));
        $loader = new BlogPageLoader($reader, static::createStub(BlogDetailReader::class), static::createStub(BlogSettings::class), $route, static::createStub(EventDispatcherInterface::class), systemConfig: $config);

        static::assertSame('category-listing', $loader->load(new Request(['categoryId' => 'untrusted']), $context, 'route-category')->getContentLayoutId());
    }

    private function blog(string $id): BlogEntity
    {
        $blog = new BlogEntity();
        $blog->id = $id;
        $blog->title = 'A headline';
        $blog->setUniqueIdentifier($id);

        return $blog;
    }

    private function contentRoute(ContentPage $contentPage): AbstractContentRoute
    {
        $route = static::createStub(AbstractContentRoute::class);
        $route->method('load')->willReturn(new ContentRouteResponse(
            new RenderResult([], LayoutReference::create($contentPage->id, $contentPage->name, null), null),
        ));

        return $route;
    }

    /**
     * @param class-string $expectedEvent
     */
    private function eventDispatcher(string $expectedEvent): EventDispatcherInterface
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf($expectedEvent));

        return $dispatcher;
    }
}
