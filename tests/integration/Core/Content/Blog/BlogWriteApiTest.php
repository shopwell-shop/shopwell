<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Blog;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class BlogWriteApiTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @param array<string, bool|string> $defaults
     */
    #[DataProvider('draftDefaults')]
    public function testSyncCreatesTaggedDraftWithExplicitOrOmittedDefaults(array $defaults): void
    {
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();
        $tagId = Uuid::randomHex();
        static::getContainer()->get('tag.repository')->create([['id' => $tagId, 'name' => $tagId]], $context);
        $browser = $this->getBrowser(permissions: ['blog:create', 'blog:update', 'tag:read', 'blog_tag:create']);

        $browser->jsonRequest('POST', '/api/_action/sync', [[
            'action' => 'upsert',
            'entity' => 'blog',
            'payload' => [[
                'id' => $id,
                'title' => 'Tagged draft',
                'slug' => $id,
                'content' => '<p>Draft content</p>',
                'tags' => [['id' => $tagId]],
                ...$defaults,
            ]],
        ]], ['HTTP_SW_LANGUAGE_ID' => Defaults::LANGUAGE_SYSTEM]);

        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent() ?: '');
        $blog = static::getContainer()->get('blog.repository')->search((new Criteria([$id]))->addAssociation('tags'), $context)->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertFalse($blog->active);
        static::assertSame('post', $blog->type);
        static::assertSame('<p>Draft content</p>', $blog->content);
        static::assertNotNull($blog->tags);
        static::assertSame([$tagId], array_keys($blog->tags));
    }

    /**
     * @return iterable<string, array{array<string, bool|string>}>
     */
    public static function draftDefaults(): iterable
    {
        yield 'all defaults supplied by the editor' => [['active' => false, 'type' => 'post']];
        yield 'only publication state supplied' => [['active' => false]];
        yield 'only type supplied' => [['type' => 'post']];
        yield 'defaults omitted' => [[]];
    }
}
