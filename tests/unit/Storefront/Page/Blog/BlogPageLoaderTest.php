<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Blog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogAuthor\BlogAuthorEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryCollection;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryEntity;
use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Content\Blog\SalesChannel\BlogListingStruct;
use Shopwell\Core\Content\Blog\Service\BlogDetailReader;
use Shopwell\Core\Content\Blog\Service\BlogDetailStruct;
use Shopwell\Core\Content\Blog\Service\BlogListingReader;
use Shopwell\Core\Content\Blog\Service\BlogSettings;
use Shopwell\Core\Content\Cms\CmsPageCollection;
use Shopwell\Core\Content\Cms\CmsPageEntity;
use Shopwell\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Page\Blog\BlogDetailPageLoadedEvent;
use Shopwell\Storefront\Page\Blog\BlogPageLoadedEvent;
use Shopwell\Storefront\Page\Blog\BlogPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The loader is the seam between the core readers and the storefront page: it must not run a query
 * of its own, it must turn a missing cms layout into null instead of an error, and it must dispatch
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
        $category->cmsPageId = 'cms-page-id';
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
            ->willReturn('cms-page-id');

        $settings = static::createStub(BlogSettings::class);
        $settings->method('getPostsPerPage')->willReturn(12);

        $cmsPage = new CmsPageEntity();
        $cmsPage->setUniqueIdentifier('cms-page-id');

        $loader = new BlogPageLoader(
            $listingReader,
            static::createStub(BlogDetailReader::class),
            $settings,
            $this->cmsPageLoader($cmsPage),
            $this->eventDispatcher(BlogPageLoadedEvent::class),
        );

        $page = $loader->load($request, $salesChannelContext, 'category-id');

        static::assertSame($blogs, $page->getBlogs());
        $categories = $page->getCategories();
        static::assertNotNull($categories);
        static::assertSame([$category], array_values($categories->getElements()));
        static::assertSame(['blog-1' => 2, 'blog-2' => 5], $page->getReadingTimes());
        static::assertSame('category-id', $page->getActiveCategoryId());
        static::assertSame('cms-page-id', $page->getCmsPageId());
        static::assertSame($cmsPage, $page->getCmsPage());
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

    public function testTheUnfilteredListingNeedsNoCmsLayoutAndFallsBackToTheBlogTitle(): void
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

        $cmsPageLoader = $this->createMock(SalesChannelCmsPageLoader::class);
        $cmsPageLoader->expects($this->never())->method('load');

        $loader = new BlogPageLoader(
            $listingReader,
            static::createStub(BlogDetailReader::class),
            static::createStub(BlogSettings::class),
            $cmsPageLoader,
            $this->eventDispatcher(BlogPageLoadedEvent::class),
        );

        $page = $loader->load($request, $salesChannelContext);

        static::assertNull($page->getActiveCategoryId());
        static::assertNull($page->getCmsPageId());
        static::assertNull($page->getCmsPage());
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
        $blog->cmsPageId = 'cms-page-id';

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

        $cmsPage = new CmsPageEntity();
        $cmsPage->setUniqueIdentifier('cms-page-id');

        $loader = new BlogPageLoader(
            static::createStub(BlogListingReader::class),
            $detailReader,
            static::createStub(BlogSettings::class),
            $this->cmsPageLoader($cmsPage),
            $this->eventDispatcher(BlogDetailPageLoadedEvent::class),
        );

        $page = $loader->loadDetail('blog-id', $request, $salesChannelContext);

        static::assertSame($blog, $page->getBlog());
        static::assertSame($author, $page->getAuthor());
        static::assertSame($cmsPage, $page->getCmsPage());
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
            static::createStub(SalesChannelCmsPageLoader::class),
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
            static::createStub(SalesChannelCmsPageLoader::class),
            static::createStub(EventDispatcherInterface::class),
        );

        $this->expectExceptionObject(BlogException::notFound('missing'));

        $loader->loadDetail('missing', new Request(), Generator::generateSalesChannelContext());
    }

    private function blog(string $id): BlogEntity
    {
        $blog = new BlogEntity();
        $blog->id = $id;
        $blog->setUniqueIdentifier($id);

        return $blog;
    }

    private function cmsPageLoader(CmsPageEntity $cmsPage): SalesChannelCmsPageLoader
    {
        $loader = static::createStub(SalesChannelCmsPageLoader::class);
        $loader->method('load')->willReturnCallback(
            static fn (Request $request, Criteria $criteria, SalesChannelContext $context): EntitySearchResult => new EntitySearchResult(
                'cms_page',
                1,
                new CmsPageCollection([$cmsPage]),
                null,
                $criteria,
                $context->getContext(),
            )
        );

        return $loader;
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
