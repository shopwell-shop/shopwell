<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Blog;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogLayoutSubscriber;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Validation\LayoutRootSourceReader;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('discovery')]
class BlogLayoutSubscriberTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private SystemConfigService $config;

    /**
     * @var array<string, array<string, mixed>|bool|float|int|string|null>
     */
    private array $originals = [];

    protected function setUp(): void
    {
        $this->config = static::getContainer()->get(SystemConfigService::class);
        foreach (['post', 'video', 'listing'] as $type) {
            $key = 'core.content_system.default_blog_' . $type . '_content_layout';
            $this->originals[$key] = $this->config->get($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originals as $key => $value) {
            $this->config->set($key, $value);
        }
    }

    public function testPostDefaultRejectsStandaloneLayout(): void
    {
        $id = $this->layout('none');
        $this->expectExceptionObject(BlogException::invalidLayoutConfiguration(ContentSystemException::rootSourceAssignmentMismatch('none', 'blog')));

        $this->config->set('core.content_system.default_blog_post_content_layout', $id);
    }

    public function testListingDefaultRejectsProductLayout(): void
    {
        $id = $this->layout('product');
        $this->expectExceptionObject(BlogException::invalidLayoutConfiguration(ContentSystemException::rootSourceAssignmentMismatch('product', 'none')));

        $this->config->set('core.content_system.default_blog_listing_content_layout', $id);
    }

    public function testMissingVideoDefaultIsRejected(): void
    {
        $id = Uuid::randomHex();
        $this->expectExceptionObject(BlogException::invalidLayoutConfiguration(ContentSystemException::contentLayoutNotFound($id)));

        $this->config->set('core.content_system.default_blog_video_content_layout', $id);
    }

    public function testConfiguredListingDefaultCannotBeDeleted(): void
    {
        $id = $this->layout('none');
        $this->config->set('core.content_system.default_blog_listing_content_layout', $id);
        $this->expectExceptionObject(BlogException::invalidLayoutConfiguration(ContentSystemException::defaultContentLayoutDeletion([$id])));

        static::getContainer()->get('content_layout.repository')->delete([['id' => $id]], Context::createDefaultContext());
    }

    public function testUnsettingDefaultAllowsDeletion(): void
    {
        $id = $this->layout('blog');
        $this->config->set('core.content_system.default_blog_video_content_layout', $id);
        $this->config->set('core.content_system.default_blog_video_content_layout', null);

        static::getContainer()->get('content_layout.repository')->delete([['id' => $id]], Context::createDefaultContext());

        static::assertFalse(static::getContainer()->get(Connection::class)->fetchOne('SELECT 1 FROM content_layout WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]));
    }

    public function testChangingBlogDefaultInvalidatesItsConfigTag(): void
    {
        $key = 'core.content_system.default_blog_video_content_layout';
        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects($this->once())->method('invalidate')->with([SystemConfigService::buildName($key)]);
        $subscriber = new BlogLayoutSubscriber(static::getContainer()->get(LayoutRootSourceReader::class), static::getContainer()->get(Connection::class), $invalidator);

        $subscriber->invalidateDefault(new SystemConfigChangedEvent($key, null, null));
    }

    public function testWritingAssignmentInvalidatesPagesThatUsedSharedDefaults(): void
    {
        $layoutId = $this->layout('blog');
        $blogId = Uuid::randomHex();
        $event = static::getContainer()->get('blog.repository')->create([[
            'id' => $blogId,
            'type' => 'post',
            'active' => false,
            'title' => 'Assigned article',
            'slug' => $blogId,
            'contentLayouts' => [['contentLayoutId' => $layoutId, 'salesChannelId' => null]],
        ]], Context::createDefaultContext());
        $invalidator = $this->createMock(CacheInvalidator::class);
        $invalidator->expects($this->once())->method('invalidate')->with(['blog-content-layout']);
        $subscriber = new BlogLayoutSubscriber(static::getContainer()->get(LayoutRootSourceReader::class), static::getContainer()->get(Connection::class), $invalidator);

        $subscriber->invalidateAssignments($event);
    }

    private function layout(string $rootSource): string
    {
        $id = Uuid::randomHex();
        static::getContainer()->get('content_layout.repository')->create([[
            'id' => $id,
            'name' => $id,
            'version' => '1.0.0',
            'rootSource' => $rootSource,
            'layout' => [['id' => Uuid::randomHex(), 'component' => 'Sw:Grid:Container']],
        ]], Context::createDefaultContext());

        return $id;
    }
}
