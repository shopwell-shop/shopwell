<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Blog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogAuthor\BlogAuthorEntity;
use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\Service\BlogCommentTreeResult;
use Shopwell\Core\Content\Cms\CmsPageEntity;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Page\Blog\BlogDetailPage;

/**
 * The detail page is the rendered article, and the template reads its defaults before the loader had
 * a chance to fill them. Comments are enabled by default but there is no thread without a loader, and
 * a reading time of zero would render as "0 min read" on an article that simply was not estimated yet.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogDetailPage::class)]
class BlogDetailPageTest extends TestCase
{
    public function testANewDetailPageCarriesSafeDefaults(): void
    {
        $page = new BlogDetailPage();

        static::assertNull($page->getBlog());
        static::assertNull($page->getRelatedBlogs());
        static::assertNull($page->getAuthor());
        static::assertNull($page->getCoverMedia());
        static::assertNull($page->getVideo());
        static::assertSame([], $page->getToc());
        static::assertSame('', $page->getContent());
        static::assertSame(1, $page->getReadingTimeMinutes());
        static::assertTrue($page->getShowAuthor());
        static::assertTrue($page->getShowReadingTime());
        static::assertTrue($page->getTocEnabled());
        static::assertNull($page->getComments());
        static::assertTrue($page->getCommentsEnabled());
        static::assertFalse($page->getCommentsAutoPublish());
        static::assertSame(10, $page->getCommentsPerPage());
        static::assertNull($page->getCmsPage());
    }

    public function testTheArticleStateTravelsToTheTemplate(): void
    {
        $blog = new BlogEntity();
        $blog->setUniqueIdentifier('blog-id');
        $blog->title = 'A headline';

        $author = new BlogAuthorEntity();
        $author->setUniqueIdentifier('author-id');
        $author->name = 'Ada';

        $page = new BlogDetailPage();
        $page->setBlog($blog);
        $page->setRelatedBlogs(new BlogCollection([$blog]));
        $page->setAuthor($author);
        $page->setCoverMedia(new MediaEntity());
        $page->setToc([['level' => 2, 'id' => 'section-1', 'title' => 'A headline']]);
        $page->setContent('<h2 id="section-1">A headline</h2>');
        $page->setReadingTimeMinutes(4);
        $page->setShowAuthor(true);
        $page->setShowReadingTime(false);
        $page->setTocEnabled(false);
        $page->setComments(new BlogCommentTreeResult());
        $page->setCommentsEnabled(false);
        $page->setCommentsAutoPublish(true);
        $page->setCommentsPerPage(25);
        $page->setCmsPage(new CmsPageEntity());

        static::assertSame($blog, $page->getBlog());
        $relatedBlogs = $page->getRelatedBlogs();
        static::assertNotNull($relatedBlogs);
        static::assertSame([$blog], array_values($relatedBlogs->getElements()));
        static::assertSame($author, $page->getAuthor());
        static::assertInstanceOf(MediaEntity::class, $page->getCoverMedia());
        static::assertSame([['level' => 2, 'id' => 'section-1', 'title' => 'A headline']], $page->getToc());
        static::assertSame('<h2 id="section-1">A headline</h2>', $page->getContent());
        static::assertSame(4, $page->getReadingTimeMinutes());
        static::assertFalse($page->getShowReadingTime());
        static::assertFalse($page->getTocEnabled());
        $comments = $page->getComments();
        static::assertNotNull($comments);
        static::assertSame(0, $comments->getTotal());
        static::assertFalse($page->getCommentsEnabled());
        static::assertTrue($page->getCommentsAutoPublish());
        static::assertSame(25, $page->getCommentsPerPage());
        static::assertInstanceOf(CmsPageEntity::class, $page->getCmsPage());
    }

    public function testThePageAnswersWithTheBlogEntityName(): void
    {
        static::assertSame('blog', (new BlogDetailPage())->getEntityName());
    }
}
