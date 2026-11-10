<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Aggregate\BlogContentLayout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogContentLayoutCollection;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogContentLayoutDefinition;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogContentLayoutEntity;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogSpecificationSource;
use Shopwell\Core\Content\Blog\BlogCollection;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Content\Blog\Service\BlogVisibilityGuard;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Shopwell\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutResolver;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Diagnostics\RootContextMapper;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogSpecificationSource::class)]
class BlogSpecificationSourceTest extends TestCase
{
    private StaticSystemConfigService $config;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->config = new StaticSystemConfigService();
        $this->context = Generator::generateSalesChannelContext();
    }

    public function testAssignedLayoutWinsOverTypeDefault(): void
    {
        $blog = $this->blog('post');
        $assignment = new BlogContentLayoutEntity();
        $assignment->setId(Uuid::randomHex());
        $assignment->setBlogId($blog->id);
        $assignment->setContentLayoutId(Uuid::randomHex());
        $this->config->set('core.content_system.default_blog_post_content_layout', Uuid::randomHex());
        $source = $this->source($blog, new BlogContentLayoutCollection([$assignment]));

        static::assertSame($assignment->getContentLayoutId(), $source->resolveLayoutId('/blog/' . $blog->id, new Request(), $this->context));
    }

    public function testUnassignedVideoUsesSharedVideoLayout(): void
    {
        $blog = $this->blog('video');
        $id = Uuid::randomHex();
        $this->config->set('core.content_system.default_blog_video_content_layout', $id, $this->context->getSalesChannelId());
        $this->config->set('core.content_system.default_blog_post_content_layout', Uuid::randomHex());
        $source = $this->source($blog);

        static::assertSame($id, $source->resolveLayoutId('/blog/' . $blog->id, new Request(), $this->context));
    }

    public function testPluginTypeCanUseGenericBlogDefault(): void
    {
        $blog = $this->blog('podcast');
        $id = Uuid::randomHex();
        $this->config->set(BlogContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $id);
        $source = $this->source($blog);

        static::assertSame($id, $source->resolveLayoutId('/blog/' . $blog->id, new Request(), $this->context));
    }

    public function testMissingAssignmentAndDefaultRetainsNamedFallbackOutcome(): void
    {
        $blog = $this->blog('post');
        $source = $this->source($blog);
        $this->expectExceptionObject(ContentSystemException::layoutAssignmentNotFound('blog', $blog->id, $this->context->getSalesChannelId()));

        $source->resolveLayoutId('/blog/' . $blog->id, new Request(), $this->context);
    }

    public function testBlogHasItsOwnRootSourceAndEntityPreviewData(): void
    {
        $blog = $this->blog('post');
        $source = $this->source($blog);
        static::assertSame('blog', $source->rootSource());
        static::assertTrue($source->supportsEntityType('blog'));
        static::assertFalse($source->supportsEntityType('landing_page'));
        static::assertTrue($source->supports('/blog/' . $blog->id, new Request(), $this->context));
        $request = new Request(['blogId' => Uuid::randomHex()]);
        $data = $source->resolveSpecificationDataForEntity($blog->id, $request, $this->context);
        static::assertTrue($request->attributes->get(BlogSpecificationSource::PREVIEW_ATTRIBUTE));
        static::assertSame(['blog', 'blogDetail'], array_column($data->dataRequirements, 'key'));
    }

    public function testPublicRenderingRejectsAnAssignedDraftBeforeReadingItsLayout(): void
    {
        $blog = $this->blog('post');
        $blog->active = false;
        $assignment = new BlogContentLayoutEntity();
        $assignment->setId(Uuid::randomHex());
        $assignment->setBlogId($blog->id);
        $assignment->setContentLayoutId(Uuid::randomHex());
        $source = $this->source($blog, new BlogContentLayoutCollection([$assignment]));
        $this->expectExceptionObject(BlogException::notFound($blog->id));

        $source->resolveLayoutId('/blog/' . $blog->id, new Request(), $this->context);
    }

    public function testPublicDataSpecificationRejectsScheduledEntry(): void
    {
        $blog = $this->blog('post');
        $blog->publishedAt = new \DateTimeImmutable('2026-11-10 12:00:00');
        $source = $this->source($blog);
        $this->expectExceptionObject(BlogException::notFound($blog->id));

        $source->resolveSpecificationData('/blog/' . $blog->id, new Request(), $this->context);
    }

    public function testDraftIsStillAvailableToTheAuthenticatedEntityPreviewSpecification(): void
    {
        $blog = $this->blog('post');
        $blog->active = false;
        $source = $this->source($blog);
        $request = new Request();

        $data = $source->resolveSpecificationDataForEntity($blog->id, $request, $this->context);

        static::assertTrue($request->attributes->get(BlogSpecificationSource::PREVIEW_ATTRIBUTE));
        static::assertSame(['blog', 'blogDetail'], array_column($data->dataRequirements, 'key'));
    }

    private function blog(string $type): BlogEntity
    {
        $blog = new BlogEntity();
        $blog->id = Uuid::randomHex();
        $blog->type = $type;
        $blog->active = true;
        $blog->setUniqueIdentifier($blog->id);

        return $blog;
    }

    private function source(BlogEntity $blog, ?BlogContentLayoutCollection $assignments = null): BlogSpecificationSource
    {
        $resolver = new EntityLayoutResolver();
        $tags = static::createStub(CacheTagCollector::class);

        return new BlogSpecificationSource(
            new StaticEntityRepository([$assignments ?? new BlogContentLayoutCollection(), $assignments ?? new BlogContentLayoutCollection()]),
            new BlogContentLayoutDefinition(),
            new EntityLayoutContextFactory($resolver, static::createStub(RootContextMapper::class), $this->config, $tags),
            new StaticEntityRepository([new BlogCollection([$blog])]),
            $this->config,
            $resolver,
            $tags,
            new BlogVisibilityGuard(new MockClock('2026-11-09 12:00:00')),
        );
    }
}
