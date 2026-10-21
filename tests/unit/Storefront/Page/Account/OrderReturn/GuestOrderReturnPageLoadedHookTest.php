<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Account\OrderReturn;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Page\Account\OrderReturn\GuestOrderReturnPage;
use Shopwell\Storefront\Page\Account\OrderReturn\GuestOrderReturnPageLoadedHook;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(GuestOrderReturnPageLoadedHook::class)]
class GuestOrderReturnPageLoadedHookTest extends TestCase
{
    public function testTheHookNameIsStable(): void
    {
        static::assertSame('guest-order-return-page-loaded', \constant(GuestOrderReturnPageLoadedHook::class . '::HOOK_NAME'));
    }

    public function testTheHookCarriesThePageAndTheContext(): void
    {
        $page = new GuestOrderReturnPage();
        $context = Generator::generateSalesChannelContext();

        $hook = new GuestOrderReturnPageLoadedHook($page, $context);

        static::assertSame(\constant(GuestOrderReturnPageLoadedHook::class . '::HOOK_NAME'), $hook->getName());
        static::assertSame($page, $hook->getPage());
        static::assertSame($context, $hook->getSalesChannelContext());
        static::assertSame($context->getContext(), $hook->getContext());
    }
}
