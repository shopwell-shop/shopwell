<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\Seo\SeoUrlRoute;

use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryEntity;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopwell\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopwell\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;

/**
 * The readable url of a category listing: `blog/category/<slug of this language>`.
 *
 * It is a route of its own and not a query parameter of the listing, because a filter is not a page: a
 * url like `/blog?category=…` is not indexable in any useful way, cannot be linked with a canonical
 * path and disappears from the sitemap. A category listing is a landing page of its own and gets its
 * own segment.
 *
 * Switched-off categories are excluded - the chip bar already hides them, and a url that leads to a
 * listing that is not offered is a dead end.
 *
 * Registered as a storefront route as well as an entity route: the entity registration alone only
 * feeds the headless template configuration, while the storefront resolves paths through the route
 * registry.
 */
#[Package('discovery')]
class BlogCategorySeoUrlRoute implements SeoUrlRouteInterface
{
    final public const ROUTE_NAME = 'frontend.blog.category';

    final public const DEFAULT_TEMPLATE = 'blog/category/{{ blogCategory.translated.slug }}';

    /**
     * @internal
     */
    public function __construct(private readonly EntityDefinition $blogCategoryDefinition)
    {
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->blogCategoryDefinition,
            self::ROUTE_NAME,
            self::DEFAULT_TEMPLATE,
            true,
            'blogCategoryId',
        );
    }

    public function prepareCriteria(Criteria $criteria, SalesChannelEntity $salesChannel): void
    {
        $criteria->addFilter(new EqualsFilter('active', true));
    }

    /**
     * The template reads `blogCategory.translated.slug`, so the entity has to travel under exactly
     * that key - the variable parser resolves the first segment of the template against this array.
     */
    public function getMapping(Entity $blogCategory, ?SalesChannelEntity $salesChannel): SeoUrlMapping
    {
        if (!$blogCategory instanceof BlogCategoryEntity && !$blogCategory instanceof PartialEntity) {
            throw BlogException::seoUrlMappingInvalidEntity(BlogCategoryEntity::class);
        }

        // Attribute based entities carry the primary key as a declared property, not through a
        // generated getId() - the base class only knows the generic unique identifier, which is the
        // join of all key columns and therefore the wrong value for a single key parameter.
        $id = $blogCategory instanceof PartialEntity ? $blogCategory->getUniqueIdentifier() : $blogCategory->id;

        return new SeoUrlMapping(
            $blogCategory,
            $this->getConfig()->getPrimaryKeyParameter($id),
            ['blogCategory' => $blogCategory->jsonSerialize()],
        );
    }
}
