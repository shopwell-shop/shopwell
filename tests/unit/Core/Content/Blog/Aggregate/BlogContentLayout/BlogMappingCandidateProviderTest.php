<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Aggregate\BlogContentLayout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogContentLayoutDefinition;
use Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout\BlogMappingCandidateProvider;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogMappingCandidateProvider::class)]
class BlogMappingCandidateProviderTest extends TestCase
{
    public function testCatalogueDeclaresOnlyCuratedBlogFields(): void
    {
        $provider = new BlogMappingCandidateProvider(new BlogContentLayoutDefinition());
        static::assertTrue($provider->supports('blog'));
        static::assertFalse($provider->supports('landing_page'));
        static::assertSame(BlogMappingCandidateProvider::registeredCandidateKeys()['blog'], array_column($provider->provide('blog'), 'path'));
    }

    public function testMappedBodyUsesTheCurrentBlogInsteadOfAuthoredExampleText(): void
    {
        $provider = new BlogMappingCandidateProvider(new BlogContentLayoutDefinition());
        $blog = new BlogEntity();
        $blog->content = '<p>Current article</p>';
        $context = new MappingSourceResolutionContext(new StoredElement('text', 'Sw:Content:Text'), ['blog' => $blog], [], new RenderingCacheContext());

        static::assertSame('<p>Current article</p>', $provider->resolveRootSource(MappingSourceReference::root('blog', 'content'), $context)->value);
        static::assertSame('post', $provider->resolveRootSource(MappingSourceReference::root('blog', 'type'), $context)->value);
    }

    public function testAbsentBlogDoesNotInventAMappedValue(): void
    {
        $provider = new BlogMappingCandidateProvider(new BlogContentLayoutDefinition());
        $context = new MappingSourceResolutionContext(new StoredElement('text', 'Sw:Content:Text'), [], [], new RenderingCacheContext());

        static::assertEquals(MappingSourceResolution::noValue(), $provider->resolveRootSource(MappingSourceReference::root('blog', 'content'), $context));
    }
}
