<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Blog;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class BlogExperienceStudioAclTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testStudioViewersCanLoadBlogAssignmentsAndPreviewChoices(): void
    {
        $browser = $this->getBrowser(permissions: ['content_layout:read', 'blog_content_layout:read', 'blog:read']);
        $browser->jsonRequest('POST', '/api/search/content-layout', ['associations' => ['blogContentLayouts' => []]]);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent() ?: '');

        $browser->jsonRequest('POST', '/api/search/blog', ['limit' => 1]);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent() ?: '');
    }

    public function testStudioViewersCannotWriteBlogAssignmentsOrContent(): void
    {
        $browser = $this->getBrowser(permissions: ['content_layout:read', 'blog_content_layout:read', 'blog:read']);
        $browser->jsonRequest('POST', '/api/blog-content-layout', ['id' => Uuid::randomHex(), 'blogId' => Uuid::randomHex(), 'contentLayoutId' => Uuid::randomHex()]);
        static::assertSame(403, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent() ?: '');

        $browser->jsonRequest('POST', '/api/blog', ['title' => 'Forbidden', 'slug' => 'forbidden']);
        static::assertSame(403, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent() ?: '');
    }
}
