<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Blog;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Sitemap\BlogUrlProvider;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('discovery')]
class BlogUrlProviderTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testTheSitemapOmitsDraftsAndScheduledEntries(): void
    {
        $draft = Uuid::randomHex();
        $undated = Uuid::randomHex();
        $elapsed = Uuid::randomHex();
        $scheduled = Uuid::randomHex();
        static::getContainer()->get('blog.repository')->create([
            ['id' => $draft, 'title' => 'Draft', 'slug' => $draft, 'publishedAt' => '2000-01-01 00:00:00'],
            ['id' => $undated, 'title' => 'Live', 'slug' => $undated, 'active' => true],
            ['id' => $elapsed, 'title' => 'Elapsed', 'slug' => $elapsed, 'active' => true, 'publishedAt' => '2000-01-01 00:00:00'],
            ['id' => $scheduled, 'title' => 'Scheduled', 'slug' => $scheduled, 'active' => true, 'publishedAt' => '2099-01-01 00:00:00'],
        ], Context::createDefaultContext());

        $result = static::getContainer()->get(BlogUrlProvider::class)->getUrls(Generator::generateSalesChannelContext(), 100, null);
        $identifiers = array_map(static fn ($url): string => $url->getIdentifier(), $result->getUrls());

        static::assertContains($undated, $identifiers);
        static::assertContains($elapsed, $identifiers);
        static::assertNotContains($draft, $identifiers);
        static::assertNotContains($scheduled, $identifiers);
    }
}
