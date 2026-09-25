<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Cookie\Hook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cookie\Hook\CookieGroupCollectHook;
use Shopwell\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacadeHookFactory;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CookieGroupCollectHook::class)]
class CookieGroupCollectHookTest extends TestCase
{
    public function testHook(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $context = Context::createDefaultContext();
        $salesChannelContext->method('getContext')->willReturn($context);

        $cookieGroups = new CookieGroupCollection();

        $hook = new CookieGroupCollectHook($cookieGroups, $salesChannelContext);

        static::assertSame('cookie-group-collect', $hook->getName());
        static::assertSame($cookieGroups, $hook->getCookieGroups());
        static::assertSame($salesChannelContext, $hook->getSalesChannelContext());
        static::assertSame($context, $hook->getContext());
    }

    public function testGetServiceIds(): void
    {
        static::assertSame(
            [
                RepositoryFacadeHookFactory::class,
                SystemConfigFacadeHookFactory::class,
                SalesChannelRepositoryFacadeHookFactory::class,
            ],
            CookieGroupCollectHook::getServiceIds()
        );
    }
}
