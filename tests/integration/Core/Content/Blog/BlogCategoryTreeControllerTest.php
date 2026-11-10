<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Blog;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class BlogCategoryTreeControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testEditorCanMoveACategoryThroughTheAdminAction(): void
    {
        $parent = $this->category();
        $moving = $this->category();
        $browser = $this->getBrowser(permissions: ['blog_category:update']);

        $browser->jsonRequest('POST', '/api/_action/blog/category/' . $moving . '/move', ['parentId' => $parent, 'afterCategoryId' => null]);

        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent() ?: '');
        $category = static::getContainer()->get('blog_category.repository')->search(new Criteria([$moving]), Context::createDefaultContext())->first();
        static::assertInstanceOf(BlogCategoryEntity::class, $category);
        static::assertSame($parent, $category->parentId);
        static::assertSame('|' . $parent . '|', $category->path);
    }

    public function testViewerCannotMoveACategory(): void
    {
        $browser = $this->getBrowser(permissions: ['blog_category:read']);
        $browser->jsonRequest('POST', '/api/_action/blog/category/' . Uuid::randomHex() . '/move', ['parentId' => null, 'afterCategoryId' => null]);

        static::assertSame(403, $browser->getResponse()->getStatusCode());
    }

    public function testInvalidParentPayloadIsRejected(): void
    {
        $browser = $this->getBrowser(permissions: ['blog_category:update']);
        $browser->jsonRequest('POST', '/api/_action/blog/category/' . Uuid::randomHex() . '/move', ['parentId' => [], 'afterCategoryId' => null]);

        static::assertSame(400, $browser->getResponse()->getStatusCode());
    }

    private function category(): string
    {
        $id = Uuid::randomHex();
        static::getContainer()->get('blog_category.repository')->create([['id' => $id, 'name' => $id, 'slug' => $id, 'active' => true]], Context::createDefaultContext());

        return $id;
    }
}
