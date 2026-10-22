<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Sitemap;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryEntity;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Seo\Exception\SeoUrlRouteConfigException;
use Shopwell\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopwell\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Shopwell\Core\Content\Sitemap\Struct\Url;
use Shopwell\Core\Content\Sitemap\Struct\UrlResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * Puts the readable blog urls into the sitemap: every entry that is live and every category that is
 * offered.
 *
 * It reads the tables directly and not through the dal, because a sitemap is generated for a whole
 * sales channel in one job and the dal would hydrate an entity per url - hundreds of objects whose only
 * use is a slug.
 *
 * The canonical url comes from `seo_url` when the shop generated one, and from the translation's slug
 * otherwise. A shop that never ran `seo:recreate` still gets a working sitemap, which is the case that
 * matters on a fresh installation.
 *
 * The provider is deliberately not split in two. Entries and categories are one list of links below
 * `/blog`, and splitting them would mean two settings an administrator has to find in the sitemap
 * configuration to switch a blog off. The entries are paged; the categories are appended to the last
 * page because a shop has tens of them and not thousands.
 */
#[Package('discovery')]
class BlogUrlProvider extends AbstractUrlProvider
{
    final public const CHANGE_FREQ = 'weekly';

    /**
     * The ceiling of categories in one sitemap run. A blog with more categories than this has a
     * navigation problem, and the sitemap is not the place to discover it.
     */
    private const CATEGORY_LIMIT = 1000;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly EntityRouteResolver $entityRouteResolver,
    ) {
    }

    public function getDecorated(): AbstractUrlProvider
    {
        throw new DecorationPatternException(self::class);
    }

    public function getName(): string
    {
        return 'blog';
    }

    public function getUrls(SalesChannelContext $context, int $limit, ?int $offset = null): UrlResult
    {
        $offset ??= 0;
        $limit = max(1, $limit);

        $entries = $this->entries($context, $limit, $offset);
        $urls = $this->toUrls($entries, BlogEntity::ENTITY_NAME, BlogEntity::class, $context);

        $nextOffset = \count($entries) === $limit ? $offset + $limit : null;

        // the last page of entries carries the categories with it, so the sitemap needs no second
        // reason to walk this provider again
        if ($nextOffset === null) {
            $categories = $this->categories($context, self::CATEGORY_LIMIT);
            $urls = [
                ...$urls,
                ...$this->toUrls($categories, BlogCategoryEntity::ENTITY_NAME, BlogCategoryEntity::class, $context),
            ];
        }

        return new UrlResult($urls, $nextOffset);
    }

    /**
     * The canonical path of every row when the shop generated one, the translated slug otherwise.
     *
     * The route name is asked from {@see EntityRouteResolver} and not written down here: the readable
     * url of a blog entry is a storefront route, and the sitemap belongs to core. Hard coding
     * `frontend.blog.detail` would couple the two packages and break the sitemap on an installation
     * that runs without a storefront. When no route is registered the lookup is skipped and every row
     * falls back to its slug, which is what a fresh installation without generated urls renders today.
     *
     * @param list<array{id: string, slug: string, created_at: string, updated_at: string|null}> $rows
     * @param class-string $resource
     *
     * @return list<Url>
     */
    private function toUrls(array $rows, string $entityName, string $resource, SalesChannelContext $context): array
    {
        if ($rows === []) {
            return [];
        }

        $canonical = [];

        foreach ($this->seoUrls($rows, $entityName, $context) as $seoUrl) {
            $canonical[$seoUrl['foreign_key']] = $seoUrl['seo_path_info'];
        }

        $urls = [];

        foreach ($rows as $row) {
            $url = new Url();
            $url->setLoc($canonical[$row['id']] ?? $row['slug']);
            $url->setLastmod(new \DateTime($row['updated_at'] ?? $row['created_at']));
            $url->setChangefreq(self::CHANGE_FREQ);
            $url->setResource($resource);
            $url->setIdentifier($row['id']);

            $urls[] = $url;
        }

        return $urls;
    }

    /**
     * The generated canonical paths of the given rows, empty when the shop has none for this entity.
     *
     * @param list<array{id: string, slug: string, created_at: string, updated_at: string|null}> $rows
     *
     * @return list<array{foreign_key: string, seo_path_info: string}>
     */
    private function seoUrls(array $rows, string $entityName, SalesChannelContext $context): array
    {
        try {
            $routeName = $this->entityRouteResolver->getRouteNameForEntityName(
                $entityName,
                $context->getSalesChannel()->getTypeId()
            );
        } catch (SeoUrlRouteConfigException) {
            // no readable url is configured for this entity, which is a valid installation state: the
            // sitemap then lists the translated slugs and nothing else
            return [];
        }

        /** @var list<array{foreign_key: string, seo_path_info: string}> $seoUrls */
        $seoUrls = $this->getSeoUrls(array_column($rows, 'id'), $routeName, $context, $this->connection);

        return $seoUrls;
    }

    /**
     * @return list<array{id: string, slug: string, created_at: string, updated_at: string|null}>
     */
    private function entries(SalesChannelContext $context, int $limit, int $offset): array
    {
        $sql = 'SELECT LOWER(HEX(b.id)) AS id,
                       t.slug,
                       b.created_at,
                       b.updated_at
                  FROM blog b
            INNER JOIN blog_translation t
                    ON t.blog_id = b.id
                   AND t.language_id = :languageId
                 WHERE (b.published_at IS NULL OR b.published_at <= :now)
              ORDER BY b.published_at DESC, b.id ASC
                 LIMIT :limit OFFSET :offset';

        /** @var list<array{id: string, slug: string, created_at: string, updated_at: string|null}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            $sql,
            [
                'languageId' => Uuid::fromHexToBytes($context->getLanguageId()),
                'now' => $this->clock->now()->format('Y-m-d H:i:s.v'),
                'limit' => $limit,
                'offset' => $offset,
            ],
            [
                'limit' => ParameterType::INTEGER,
                'offset' => ParameterType::INTEGER,
            ]
        );

        return $rows;
    }

    /**
     * @return list<array{id: string, slug: string, created_at: string, updated_at: string|null}>
     */
    private function categories(SalesChannelContext $context, int $limit): array
    {
        $sql = 'SELECT LOWER(HEX(c.id)) AS id,
                       t.slug,
                       c.created_at,
                       c.updated_at
                  FROM blog_category c
            INNER JOIN blog_category_translation t
                    ON t.blog_category_id = c.id
                   AND t.language_id = :languageId
                 WHERE c.active = 1
              ORDER BY c.id ASC
                 LIMIT :limit';

        /** @var list<array{id: string, slug: string, created_at: string, updated_at: string|null}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            $sql,
            [
                'languageId' => Uuid::fromHexToBytes($context->getLanguageId()),
                'limit' => $limit,
            ],
            ['limit' => ParameterType::INTEGER]
        );

        return $rows;
    }
}
