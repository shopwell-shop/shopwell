<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Blog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Page\Blog\BlogPage;
use Shopwell\Storefront\Page\Blog\BlogPageLoadedHook;

/**
 * The hook is the extension point scripts subscribe to, so its name is a public contract: a renamed
 * constant silently stops every script of every shop. The page and the context have to be the same
 * instances the controller used, otherwise a script would mutate a copy.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogPageLoadedHook::class)]
class BlogPageLoadedHookTest extends TestCase
{
    public function testTheHookCarriesThePageAndTheContextOfTheRequest(): void
    {
        $page = new BlogPage();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $hook = new BlogPageLoadedHook($page, $salesChannelContext);

        static::assertSame('blog-page-loaded', $hook->getName());
        static::assertSame(BlogPageLoadedHook::HOOK_NAME, $hook->getName());
        static::assertSame($page, $hook->getPage());
        static::assertSame($salesChannelContext, $hook->getSalesChannelContext());
        static::assertSame($salesChannelContext->getContext(), $hook->getContext());
    }
}
