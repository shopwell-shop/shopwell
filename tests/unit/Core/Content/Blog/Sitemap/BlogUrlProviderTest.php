<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Sitemap;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryEntity;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\Sitemap\BlogUrlProvider;
use Shopwell\Core\Content\Seo\Exception\SeoUrlRouteConfigException;
use Shopwell\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopwell\Core\Content\Sitemap\Struct\Url;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Generator;

/**
 * The sitemap is generated in batches, so the offset the provider reports decides whether the next
 * run happens at all and whether the categories are listed exactly once. The other moving part is
 * the canonical url: a shop that never ran `seo:recreate` has no `seo_url` rows and must still get a
 * working sitemap built from the translation slugs.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogUrlProvider::class)]
class BlogUrlProviderTest extends TestCase
{
    public function testTheProviderIsNamedBlogAndRefusesToBeDecorated(): void
    {
        $provider = $this->provider(static::createStub(Connection::class));

        static::assertSame('blog', $provider->getName());

        $this->expectException(DecorationPatternException::class);
        $provider->getDecorated();
    }

    public function testAPageWithRoomForMoreEntriesIsTheLastOneAndCarriesTheCategories(): void
    {
        $entryId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnCallback(
            static function (string $sql, array $params) use ($entryId, $categoryId): array {
                if (str_contains($sql, 'FROM blog b')) {
                    return [[
                        'id' => $entryId,
                        'slug' => 'a-post',
                        'created_at' => '2026-01-01 08:00:00',
                        'updated_at' => '2026-01-02 09:00:00',
                    ]];
                }

                if (str_contains($sql, 'FROM seo_url')) {
                    return ($params['routeName'] ?? '') === 'frontend.blog.detail'
                        ? [['foreign_key' => $entryId, 'seo_path_info' => '/blog/a-post']]
                        : [['foreign_key' => $categoryId, 'seo_path_info' => '/blog/category/guides']];
                }

                return [[
                    'id' => $categoryId,
                    'slug' => 'guides',
                    'created_at' => '2026-01-01 08:00:00',
                    'updated_at' => null,
                ]];
            }
        );

        $result = $this->provider($connection)->getUrls(
            Generator::generateSalesChannelContext(),
            5,
            null,
        );

        static::assertNull($result->getNextOffset());
        static::assertCount(2, $result->getUrls());

        $entry = $result->getUrls()[0];
        static::assertSame('/blog/a-post', $entry->getLoc());
        static::assertSame(BlogUrlProvider::CHANGE_FREQ, $entry->getChangefreq());
        static::assertSame(BlogEntity::class, $entry->getResource());
        static::assertSame($entryId, $entry->getIdentifier());
        static::assertSame('2026-01-02', $entry->getLastmod()->format('Y-m-d'));

        $category = $result->getUrls()[1];
        static::assertSame('/blog/category/guides', $category->getLoc());
        static::assertSame(BlogCategoryEntity::class, $category->getResource());
        static::assertSame('2026-01-01', $category->getLastmod()->format('Y-m-d'));
    }

    public function testAFullPageReportsTheNextOffsetAndDefersTheCategoriesToTheLastPage(): void
    {
        $calls = [];
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnCallback(
            static function (string $sql) use (&$calls, $firstId, $secondId): array {
                $calls[] = $sql;

                if (str_contains($sql, 'FROM blog b')) {
                    return [
                        ['id' => $firstId, 'slug' => 'a', 'created_at' => '2026-01-01 08:00:00', 'updated_at' => null],
                        ['id' => $secondId, 'slug' => 'b', 'created_at' => '2026-01-01 08:00:00', 'updated_at' => null],
                    ];
                }

                return [];
            }
        );

        $result = $this->provider($connection)->getUrls(Generator::generateSalesChannelContext(), 2, 4);

        static::assertSame(6, $result->getNextOffset());
        static::assertCount(2, $result->getUrls());
        static::assertSame(['a', 'b'], array_map(static fn (Url $url): string => $url->getLoc(), $result->getUrls()));

        foreach ($calls as $sql) {
            static::assertStringNotContainsString('FROM blog_category c', $sql);
        }
    }

    public function testWithoutAGeneratedSeoUrlTheTranslationSlugIsTheCanonicalPath(): void
    {
        $resolver = static::createStub(EntityRouteResolver::class);
        $resolver->method('getRouteNameForEntityName')->willThrowException(
            SeoUrlRouteConfigException::routeConfigNotFoundForEntityName(BlogEntity::ENTITY_NAME)
        );

        $queries = [];
        $entryId = Uuid::randomHex();

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturnCallback(
            static function (string $sql) use (&$queries, $entryId): array {
                $queries[] = $sql;

                if (str_contains($sql, 'FROM blog b')) {
                    return [['id' => $entryId, 'slug' => 'the-post', 'created_at' => '2026-01-01 08:00:00', 'updated_at' => null]];
                }

                return [];
            }
        );

        $result = $this->provider($connection, $resolver)->getUrls(Generator::generateSalesChannelContext(), 10);

        static::assertSame('the-post', $result->getUrls()[0]->getLoc());

        // the missing route config was the answer, so no seo_url lookup was attempted for the entry
        static::assertCount(2, $queries);
        static::assertStringNotContainsString('FROM seo_url', implode(' ', $queries));
    }

    private function provider(Connection $connection, ?EntityRouteResolver $resolver = null): BlogUrlProvider
    {
        $clock = static::createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-03 12:00:00', new \DateTimeZone('UTC')));

        if ($resolver === null) {
            $resolver = static::createStub(EntityRouteResolver::class);
            $resolver->method('getRouteNameForEntityName')->willReturnCallback(
                static fn (string $entityName): string => $entityName === BlogCategoryEntity::ENTITY_NAME
                    ? 'frontend.blog.category'
                    : 'frontend.blog.detail'
            );
        }

        return new BlogUrlProvider($connection, $clock, $resolver);
    }
}
