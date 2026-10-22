<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Framework\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryEntity;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\BlogCategorySeoUrlRoute;

/**
 * The route turns a category into `blog/category/<slug>` and has to exclude switched-off categories:
 * a category the chip bar no longer offers must not keep a reachable, indexable landing page. The
 * mapping keys matter as much as the value - the template resolves `blogCategory.translated.slug`
 * against the array under exactly that name.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogCategorySeoUrlRoute::class)]
class BlogCategorySeoUrlRouteTest extends TestCase
{
    public function testTheConfigDescribesTheCategoryRouteAndItsPrimaryKey(): void
    {
        $definition = static::createStub(EntityDefinition::class);

        $route = new BlogCategorySeoUrlRoute($definition);
        $config = $route->getConfig();

        static::assertSame($definition, $config->getDefinition());
        static::assertSame('frontend.blog.category', $config->getRouteName());
        static::assertSame('blog/category/{{ blogCategory.translated.slug }}', $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
        static::assertSame(['blogCategoryId' => 'category-id'], $config->getPrimaryKeyParameter('category-id'));
    }

    public function testOnlyActiveCategoriesArePreparedForTheGenerator(): void
    {
        $criteria = new Criteria();

        (new BlogCategorySeoUrlRoute(static::createStub(EntityDefinition::class)))
            ->prepareCriteria($criteria, new SalesChannelEntity());

        $filters = $criteria->getFilters();

        static::assertCount(1, $filters);
        static::assertInstanceOf(EqualsFilter::class, $filters[0]);
        static::assertSame('active', $filters[0]->getField());
        static::assertTrue($filters[0]->getValue());
    }

    public function testTheMappingExposesTheCategoryUnderTheNameTheTemplateReads(): void
    {
        $category = new BlogCategoryEntity();
        $category->id = 'category-id';
        $category->name = 'Guides';
        $category->slug = 'guides';
        $category->setUniqueIdentifier('category-id');

        $mapping = (new BlogCategorySeoUrlRoute(static::createStub(EntityDefinition::class)))
            ->getMapping($category, null);

        static::assertSame($category, $mapping->getEntity());
        static::assertSame(['blogCategoryId' => 'category-id'], $mapping->getInfoPathContext());
        static::assertArrayHasKey('blogCategory', $mapping->getSeoPathInfoContext());
        static::assertSame('guides', $mapping->getSeoPathInfoContext()['blogCategory']['slug']);
        static::assertNull($mapping->getError());
    }

    public function testAPartialEntityMapsThroughItsUniqueIdentifier(): void
    {
        $category = new PartialEntity([
            'id' => 'partial-id',
            'translated' => ['slug' => 'guides'],
        ]);

        $mapping = (new BlogCategorySeoUrlRoute(static::createStub(EntityDefinition::class)))
            ->getMapping($category, null);

        static::assertSame(['blogCategoryId' => 'partial-id'], $mapping->getInfoPathContext());
        static::assertSame('partial-id', $mapping->getEntity()->getUniqueIdentifier());
    }

    public function testAForeignEntityIsRejectedInsteadOfBeingMappedSilently(): void
    {
        $route = new BlogCategorySeoUrlRoute(static::createStub(EntityDefinition::class));

        try {
            $route->getMapping(new MediaEntity(), null);
            static::fail('The route mapped an entity it does not own.');
        } catch (BlogException $exception) {
            static::assertSame(BlogException::SEO_URL_MAPPING_INVALID_ENTITY, $exception->getErrorCode());
            static::assertSame(500, $exception->getStatusCode());
        }
    }
}
