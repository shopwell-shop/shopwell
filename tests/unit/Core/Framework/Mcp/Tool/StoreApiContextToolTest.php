<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\Context\StoreApiMcpContextProvider;
use Shopwell\Core\System\SalesChannel\Mcp\Tool\StoreApiContextTool;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiContextTool::class)]
class StoreApiContextToolTest extends TestCase
{
    public function testReturnsCurrentStoreApiContext(): void
    {
        $customer = new CustomerEntity();
        $customer->setId('customer-id');

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn('sales-channel-id');
        $salesChannelContext->method('getToken')->willReturn('context-token');
        $salesChannelContext->method('getLanguageId')->willReturn('language-id');
        $salesChannelContext->method('getCurrencyId')->willReturn('currency-id');
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $contextProvider = static::createStub(StoreApiMcpContextProvider::class);
        $contextProvider->method('getSalesChannelContext')->willReturn($salesChannelContext);

        $tool = new StoreApiContextTool($contextProvider);
        $data = json_decode($tool(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame('sales-channel-id', $data['data']['salesChannelId']);
        static::assertSame('context-token', $data['data']['token']);
        static::assertSame('language-id', $data['data']['languageId']);
        static::assertSame('currency-id', $data['data']['currencyId']);
        static::assertTrue($data['data']['customerAuthenticated']);
        static::assertSame('customer-id', $data['data']['customerId']);
    }

    public function testReturnsErrorWithoutStoreApiContext(): void
    {
        $contextProvider = static::createStub(StoreApiMcpContextProvider::class);
        $contextProvider->method('getSalesChannelContext')->willReturn(null);

        $tool = new StoreApiContextTool($contextProvider);
        $data = json_decode($tool(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('No Store API sales-channel context', $data['error']);
    }
}
