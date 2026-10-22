<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\Seo\SeoUrlRoute;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopwell\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopwell\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;

/**
 * The readable url of an entry: `blog/<slug of this language>`.
 *
 * The route lives in the storefront and not in core, because it is the storefront that serves the url
 * it generates. Core ships the sitemap, which asks
 * {@see \Shopwell\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver} for the route name instead of
 * importing this class.
 *
 * The slug lives in the translation, so the url of an article differs per language without a second
 * column and without a second route - the template reads `blog.translated.slug`, which the seo url
 * persister resolves in the language of the sales channel it is generating for.
 *
 * Only dated entries get a url. A scheduled article must not appear in a sitemap or in a generated url
 * list before it is readable: a url that answers 404 is worse than no url, because a search engine
 * remembers it.
 *
 * The route is a full storefront route and not only an entity route. An entity route alone is enough
 * for the headless template configuration, but the storefront resolves a request path through the
 * route registry - without the registration there the generated url would never be written and a
 * visitor opening `/blog/<slug>` would land on the catch-all.
 */
#[Package('discovery')]
class BlogSeoUrlRoute implements SeoUrlRouteInterface
{
    final public const ROUTE_NAME = 'frontend.blog.detail';

    final public const DEFAULT_TEMPLATE = 'blog/{{ blog.translated.slug }}';

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityDefinition $blogDefinition,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->blogDefinition,
            self::ROUTE_NAME,
            self::DEFAULT_TEMPLATE,
            true,
            'blogId',
        );
    }

    /**
     * The same visibility rule the storefront applies, expressed as a filter: an entry without a date
     * is live, an entry dated in the future is not.
     */
    public function prepareCriteria(Criteria $criteria, SalesChannelEntity $salesChannel): void
    {
        $criteria->addFilter(new OrFilter([
            new EqualsFilter('publishedAt', null),
            new RangeFilter('publishedAt', [RangeFilter::LTE => $this->clock->now()->format('Y-m-d H:i:s.v')]),
        ]));
    }

    /**
     * Hands the generator the entity together with the parameters the target route needs.
     *
     * The template reads `blog.translated.slug`, so the entity has to travel as `blog`; without the
     * key the template would render an empty path and the url would be skipped as invalid.
     */
    public function getMapping(Entity $blog, ?SalesChannelEntity $salesChannel): SeoUrlMapping
    {
        if (!$blog instanceof BlogEntity && !$blog instanceof PartialEntity) {
            throw BlogException::seoUrlMappingInvalidEntity(BlogEntity::class);
        }

        // Attribute based entities carry the primary key as a declared property, not through a
        // generated getId() - the base class only knows the generic unique identifier, which is the
        // join of all key columns and therefore the wrong value for a single key parameter.
        $id = $blog instanceof PartialEntity ? $blog->getUniqueIdentifier() : $blog->id;

        return new SeoUrlMapping(
            $blog,
            $this->getConfig()->getPrimaryKeyParameter($id),
            ['blog' => $blog->jsonSerialize()],
        );
    }
}
