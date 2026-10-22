<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Blog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryCollection;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryEntity;
use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Cms\CmsPageEntity;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Page\Blog\BlogPage;

/**
 * A freshly constructed page is what a template reads when the loader only filled half of it, so the
 * defaults have to render something instead of fatalling on a null collection. The interesting part
 * is therefore not the setters but the state before any of them ran.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogPage::class)]
class BlogPageTest extends TestCase
{
    public function testANewPageRendersAnEmptyButUsableListing(): void
    {
        $page = new BlogPage();

        static::assertNull($page->getBlogs());
        static::assertNull($page->getCategories());
        static::assertNull($page->getCoverMedia());
        static::assertSame([], $page->getReadingTimes());
        static::assertNull($page->getActiveCategoryId());
        static::assertNull($page->getCmsPageId());
        static::assertNull($page->getCmsPage());
        static::assertSame(1, $page->getPageNumber());
        static::assertSame(0, $page->getTotalPages());
        static::assertSame(0, $page->getTotal());
        static::assertSame(12, $page->getLimit());
        static::assertTrue($page->getShowAuthor());
        static::assertTrue($page->getShowReadingTime());
        static::assertNull($page->getMetaInformation());
    }

    public function testThePageAnswersWithTheBlogEntityName(): void
    {
        static::assertSame('blog', (new BlogPage())->getEntityName());
    }

    public function testTheListingStateTravelsToTheTemplate(): void
    {
        $blog = new BlogEntity();
        $blog->setUniqueIdentifier('blog-id');

        $category = new BlogCategoryEntity();
        $category->id = 'category-id';
        $category->setUniqueIdentifier('category-id');

        $page = new BlogPage();
        $page->setBlogs(new BlogCollection([$blog]));
        $page->setCategories(new BlogCategoryCollection([$category]));
        $page->setCoverMedia(new MediaCollection());
        $page->setReadingTimes(['blog-id' => 7]);
        $page->setActiveCategoryId('category-id');
        $page->setCmsPageId('cms-page-id');
        $page->setCmsPage(new CmsPageEntity());
        $page->setPageNumber(3);
        $page->setTotalPages(5);
        $page->setTotal(48);
        $page->setLimit(12);
        $page->setShowAuthor(false);
        $page->setShowReadingTime(false);

        $blogs = $page->getBlogs();
        static::assertNotNull($blogs);
        static::assertSame([$blog], array_values($blogs->getElements()));

        $categories = $page->getCategories();
        static::assertNotNull($categories);
        static::assertSame([$category], array_values($categories->getElements()));
        static::assertSame(['blog-id' => 7], $page->getReadingTimes());
        static::assertSame('category-id', $page->getActiveCategoryId());
        static::assertSame('cms-page-id', $page->getCmsPageId());
        static::assertInstanceOf(CmsPageEntity::class, $page->getCmsPage());
        static::assertSame(3, $page->getPageNumber());
        static::assertSame(5, $page->getTotalPages());
        static::assertSame(48, $page->getTotal());
        static::assertFalse($page->getShowAuthor());
        static::assertFalse($page->getShowReadingTime());
    }
}
