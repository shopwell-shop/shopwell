<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Mcp\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\Context\StoreApiMcpContextProvider;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiMcpContextProvider::class)]
class StoreApiMcpContextProviderTest extends TestCase
{
    public function testReturnsSalesChannelContextFromRequest(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $provider = new StoreApiMcpContextProvider($requestStack);

        static::assertSame($salesChannelContext, $provider->getSalesChannelContext());
    }

    public function testReturnsContextFromSalesChannelContext(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $provider = new StoreApiMcpContextProvider($requestStack);

        static::assertSame($context, $provider->getContext());
    }

    public function testReturnsNullWhenNoSalesChannelContextExists(): void
    {
        $provider = new StoreApiMcpContextProvider(new RequestStack());

        static::assertNull($provider->getSalesChannelContext());
        static::assertSame(Context::createCLIContext()->getSource()::class, $provider->getContext()->getSource()::class);
    }
}
