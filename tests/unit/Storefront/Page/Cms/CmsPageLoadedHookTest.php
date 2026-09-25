<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\CmsPageEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Page\Cms\CmsPageLoadedHook;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CmsPageLoadedHook::class)]
class CmsPageLoadedHookTest extends TestCase
{
    public function testCmsPageLoadedHook(): void
    {
        $page = new CmsPageEntity();
        $hook = new CmsPageLoadedHook($page, Generator::generateSalesChannelContext());
        static::assertSame('cms-page-loaded', $hook->getName());
        static::assertSame($page, $hook->getPage());
    }
}
