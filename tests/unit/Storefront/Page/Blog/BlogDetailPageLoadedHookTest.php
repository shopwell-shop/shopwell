<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Blog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Page\Blog\BlogDetailPage;
use Shopwell\Storefront\Page\Blog\BlogDetailPageLoadedHook;

/**
 * Same contract as the listing hook, for the article page: scripts address the hook by its name and
 * receive the page object the controller is about to render, so both must be identical instances.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogDetailPageLoadedHook::class)]
class BlogDetailPageLoadedHookTest extends TestCase
{
    public function testTheHookCarriesThePageAndTheContextOfTheRequest(): void
    {
        $page = new BlogDetailPage();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $hook = new BlogDetailPageLoadedHook($page, $salesChannelContext);

        static::assertSame('blog-detail-page-loaded', $hook->getName());
        static::assertSame(BlogDetailPageLoadedHook::HOOK_NAME, $hook->getName());
        static::assertSame($page, $hook->getPage());
        static::assertSame($salesChannelContext, $hook->getSalesChannelContext());
        static::assertSame($salesChannelContext->getContext(), $hook->getContext());
    }
}
