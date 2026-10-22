<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Page\Account\OrderReturn;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Page\Account\OrderReturn\AccountOrderReturnPage;
use Shopwell\Storefront\Page\Account\OrderReturn\AccountOrderReturnPageLoadedHook;

/**
 * The hook name is what a script in the administration subscribes to, so it is part of the public
 * contract and may not change silently.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(AccountOrderReturnPageLoadedHook::class)]
class AccountOrderReturnPageLoadedHookTest extends TestCase
{
    public function testTheHookNameIsStable(): void
    {
        static::assertSame('account-order-return-page-loaded', \constant(AccountOrderReturnPageLoadedHook::class . '::HOOK_NAME'));
    }

    public function testTheHookCarriesThePageAndTheContext(): void
    {
        $page = new AccountOrderReturnPage();
        $context = Generator::generateSalesChannelContext();

        $hook = new AccountOrderReturnPageLoadedHook($page, $context);

        static::assertSame(\constant(AccountOrderReturnPageLoadedHook::class . '::HOOK_NAME'), $hook->getName());
        static::assertSame($page, $hook->getPage());
        static::assertSame($context, $hook->getSalesChannelContext());
        static::assertSame($context->getContext(), $hook->getContext());
    }
}
