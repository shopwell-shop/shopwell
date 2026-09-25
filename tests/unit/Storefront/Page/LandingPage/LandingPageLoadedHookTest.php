<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\LandingPage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Page\LandingPage\LandingPage;
use Shopwell\Storefront\Page\LandingPage\LandingPageLoadedHook;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LandingPageLoadedHook::class)]
class LandingPageLoadedHookTest extends TestCase
{
    public function testLandingPageLoadedHook(): void
    {
        $page = new LandingPage();
        $context = Generator::generateSalesChannelContext();

        $hook = new LandingPageLoadedHook($page, $context);
        static::assertSame('landing-page-loaded', $hook->getName());
        static::assertSame($page, $hook->getPage());
    }
}
