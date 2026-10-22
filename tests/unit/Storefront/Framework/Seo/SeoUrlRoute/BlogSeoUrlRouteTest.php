<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Framework\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryEntity;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\BlogCategorySeoUrlRoute;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\BlogSeoUrlRoute;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Response;

/**
 * A seo url route is configuration, and configuration is where a readable url silently stops working:
 * a route name that does not match the controller, a template that reads the untranslated slug, a
 * primary key parameter that the controller does not accept. None of those raise an error - they
 * produce a page with a numeric url and nobody notices for a month.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogSeoUrlRoute::class)]
#[CoversClass(BlogCategorySeoUrlRoute::class)]
class BlogSeoUrlRouteTest extends TestCase
{
    public function testTheEntryRouteIsTheRouteTheControllerServes(): void
    {
        $config = (new BlogSeoUrlRoute(new BlogRouteTestDefinition(), new MockClock('now', 'UTC')))->getConfig();

        static::assertSame('frontend.blog.detail', $config->getRouteName());
        static::assertSame('blog', $config->getDefinition()->getEntityName());
    }

    public function testTheEntryTemplateReadsTheTranslatedSlug(): void
    {
        $config = (new BlogSeoUrlRoute(new BlogRouteTestDefinition(), new MockClock('now', 'UTC')))->getConfig();

        // the untranslated slug would give every language the same url, which is the single mistake
        // that makes a multilingual blog unindexable
        static::assertStringContainsString('blog.translated.slug', $config->getTemplate());
        static::assertStringStartsWith('blog/', $config->getTemplate());
    }

    public function testTheEntryRouteFillsTheParameterTheControllerExpects(): void
    {
        $config = (new BlogSeoUrlRoute(new BlogRouteTestDefinition(), new MockClock('now', 'UTC')))->getConfig();

        static::assertSame(['blogId' => 'abc123'], $config->getPrimaryKeyParameter('abc123'));
    }

    public function testScheduledEntriesAreExcludedFromUrlGeneration(): void
    {
        $route = new BlogSeoUrlRoute(new BlogRouteTestDefinition(), new MockClock('2026-01-01 10:00:00', 'UTC'));
        $criteria = new Criteria();

        $route->prepareCriteria($criteria, new SalesChannelEntity());

        // an undated entry plus a range filter is what the storefront applies, expressed as a filter
        static::assertCount(1, $criteria->getFilters());
    }

    public function testTheCategoryRouteIsItsOwnRouteAndNotAQueryParameter(): void
    {
        $config = (new BlogCategorySeoUrlRoute(new BlogCategoryRouteTestDefinition()))->getConfig();

        static::assertSame('frontend.blog.category', $config->getRouteName());
        static::assertSame('blog_category', $config->getDefinition()->getEntityName());
        static::assertStringContainsString('blogCategory.translated.slug', $config->getTemplate());
        static::assertSame(['blogCategoryId' => 'abc123'], $config->getPrimaryKeyParameter('abc123'));
    }

    public function testOnlyOfferedCategoriesGetAUrl(): void
    {
        $route = new BlogCategorySeoUrlRoute(new BlogCategoryRouteTestDefinition());
        $criteria = new Criteria();

        $route->prepareCriteria($criteria, new SalesChannelEntity());

        static::assertCount(1, $criteria->getFilters());
    }

    /**
     * The generator renders the template against the array returned here. A wrong key does not fail -
     * the template renders an empty path, the url is dropped as invalid and the entry silently loses
     * its readable address.
     */
    public function testTheEntryMappingProvidesWhatTheTemplateReads(): void
    {
        $route = new BlogSeoUrlRoute(new BlogRouteTestDefinition(), new MockClock('now', 'UTC'));

        $blog = new BlogEntity();
        $blog->id = '01a15db17866710bba3c7cf35fb81592';
        $blog->title = 'An entry';
        $blog->slug = 'an-entry';
        $blog->setUniqueIdentifier($blog->id);

        $mapping = $route->getMapping($blog, new SalesChannelEntity());

        static::assertSame(['blogId' => $blog->id], $mapping->getInfoPathContext());
        static::assertArrayHasKey('blog', $mapping->getSeoPathInfoContext());
        static::assertSame($blog, $mapping->getEntity());
        static::assertNull($mapping->getError());
    }

    public function testTheCategoryMappingProvidesWhatTheTemplateReads(): void
    {
        $route = new BlogCategorySeoUrlRoute(new BlogCategoryRouteTestDefinition());

        $category = new BlogCategoryEntity();
        $category->id = '01a15db17866710bba3c7cf35fb81593';
        $category->name = 'Guides';
        $category->slug = 'guides';
        $category->setUniqueIdentifier($category->id);

        $mapping = $route->getMapping($category, new SalesChannelEntity());

        static::assertSame(['blogCategoryId' => $category->id], $mapping->getInfoPathContext());
        static::assertArrayHasKey('blogCategory', $mapping->getSeoPathInfoContext());
    }

    /**
     * A route registered against the wrong definition is a programming error, and it has to fail
     * loudly: mapped silently, every url of the other entity would be written with a blog segment.
     */
    public function testTheEntryMappingRefusesAForeignEntity(): void
    {
        $route = new BlogSeoUrlRoute(new BlogRouteTestDefinition(), new MockClock('now', 'UTC'));

        $category = new BlogCategoryEntity();
        $category->id = '01a15db17866710bba3c7cf35fb81594';

        try {
            $route->getMapping($category, new SalesChannelEntity());
            static::fail('Expected a BlogException for a foreign entity');
        } catch (BlogException $exception) {
            static::assertSame(BlogException::SEO_URL_MAPPING_INVALID_ENTITY, $exception->getErrorCode());
            static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        }
    }
}

/**
 * The definition the route is registered against.
 *
 * A real one and not a mock: the route only reads the entity name from it, and a hand written
 * definition keeps the test free of container and mock expectations. It declares no constructor on
 * purpose - the base constructor is deprecated and is not needed to answer a name.
 *
 * @internal
 */
class BlogRouteTestDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'blog';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
        ]);
    }
}

/**
 * @internal
 */
class BlogCategoryRouteTestDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'blog_category';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
        ]);
    }
}
