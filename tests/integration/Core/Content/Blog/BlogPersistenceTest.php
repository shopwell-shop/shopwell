<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Blog;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\Service\BlogVisibilityGuard;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Tag\TagEntity;

/**
 * @internal
 */
#[Package('discovery')]
class BlogPersistenceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testNewEntriesDefaultToDraftAndPostThroughTheRepository(): void
    {
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $repository = static::getContainer()->get('blog.repository');
        $repository->create([['id' => $id, 'title' => 'Draft', 'slug' => $id]], $context);

        $blog = $repository->search(new Criteria([$id]), $context)->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertFalse($blog->active);
        static::assertSame('post', $blog->type);
        static::assertNull($blog->publishedAt);
    }

    public function testUpdatingContentPreservesExplicitTypeAndPublicationState(): void
    {
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $repository = static::getContainer()->get('blog.repository');
        $repository->create([['id' => $id, 'title' => 'Video', 'slug' => $id, 'type' => 'video', 'active' => true]], $context);
        $repository->update([['id' => $id, 'title' => 'Updated video']], $context);

        $blog = $repository->search(new Criteria([$id]), $context)->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->active);
        static::assertSame('video', $blog->type);
        static::assertSame('Updated video', $blog->title);
    }

    public function testTagsReuseSystemTagsAndMappingDeletionPreservesTheTag(): void
    {
        $blogId = Uuid::randomHex();
        $tagId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $repository = static::getContainer()->get('blog.repository');
        static::getContainer()->get('tag.repository')->create([['id' => $tagId, 'name' => $tagId]], $context);
        $repository->create([['id' => $blogId, 'title' => 'Tagged', 'slug' => $blogId, 'tags' => [['id' => $tagId]]]], $context);
        $criteria = (new Criteria([$blogId]))->addAssociation('tags');

        $blog = $repository->search($criteria, $context)->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertNotNull($blog->tags);
        static::assertCount(1, $blog->tags);
        static::assertInstanceOf(TagEntity::class, $blog->tags[$tagId]);
        static::assertSame($tagId, $blog->tags[$tagId]->getName());

        $tagCriteria = (new Criteria([$tagId]))->addAssociation('blogs');
        $tag = static::getContainer()->get('tag.repository')->search($tagCriteria, $context)->first();
        static::assertInstanceOf(TagEntity::class, $tag);
        static::assertNotNull($tag->getBlogs());
        static::assertSame([$blogId], array_values($tag->getBlogs()->getIds()));
        static::assertFalse($tag->hasExtension('blogs'));

        static::getContainer()->get('blog_tag.repository')->delete([['blogId' => $blogId, 'tagId' => $tagId]], $context);
        $untagged = $repository->search($criteria, $context)->first();
        static::assertInstanceOf(BlogEntity::class, $untagged);
        static::assertSame([], $untagged->tags);
        static::assertSame([$tagId], static::getContainer()->get('tag.repository')->searchIds(new Criteria([$tagId]), $context)->getIds());
        $tag = static::getContainer()->get('tag.repository')->search($tagCriteria, $context)->first();
        static::assertInstanceOf(TagEntity::class, $tag);
        static::assertNotNull($tag->getBlogs());
        static::assertCount(0, $tag->getBlogs());
    }

    public function testDeletingASystemTagKeepsItsArticles(): void
    {
        $blogId = Uuid::randomHex();
        $tagId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $repository = static::getContainer()->get('blog.repository');
        $repository->create([[
            'id' => $blogId,
            'title' => 'Tagged article',
            'slug' => $blogId,
            'tags' => [['id' => $tagId, 'name' => $tagId]],
        ]], $context);

        static::getContainer()->get('tag.repository')->delete([['id' => $tagId]], $context);

        $blog = $repository->search((new Criteria([$blogId]))->addAssociation('tags'), $context)->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame([], $blog->tags);
    }

    public function testVisibilityFilterKeepsOnlyActiveElapsedAndUndatedEntries(): void
    {
        $context = Context::createDefaultContext();
        $repository = static::getContainer()->get('blog.repository');
        $draft = Uuid::randomHex();
        $undated = Uuid::randomHex();
        $elapsed = Uuid::randomHex();
        $scheduled = Uuid::randomHex();
        $repository->create([
            ['id' => $draft, 'title' => 'Draft', 'slug' => $draft, 'publishedAt' => '2000-01-01 00:00:00'],
            ['id' => $undated, 'title' => 'Live', 'slug' => $undated, 'active' => true],
            ['id' => $elapsed, 'title' => 'Elapsed', 'slug' => $elapsed, 'active' => true, 'publishedAt' => '2000-01-01 00:00:00'],
            ['id' => $scheduled, 'title' => 'Scheduled', 'slug' => $scheduled, 'active' => true, 'publishedAt' => '2099-01-01 00:00:00'],
        ], $context);
        $criteria = new Criteria([$draft, $undated, $elapsed, $scheduled]);
        $criteria->addFilter(static::getContainer()->get(BlogVisibilityGuard::class)->getVisibilityFilter());

        static::assertEqualsCanonicalizing([$undated, $elapsed], $repository->searchIds($criteria, $context)->getIds());
    }
}
