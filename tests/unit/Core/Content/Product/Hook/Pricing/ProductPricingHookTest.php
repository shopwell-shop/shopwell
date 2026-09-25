<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Hook\Pricing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Facade\PriceFactoryFactory;
use Shopwell\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopwell\Core\Content\Product\Hook\Pricing\ProductPricingHook;
use Shopwell\Core\Content\Product\Hook\Pricing\ProductProxy;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacadeHookFactory;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductPricingHook::class)]
class ProductPricingHookTest extends TestCase
{
    public function testGetProducts(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);

        $productProxy = new ProductProxy(
            (new SalesChannelProductEntity())->assign(['name' => 'foo']),
            $salesChannelContext,
            static::createStub(ScriptPriceStubs::class)
        );
        $productPricingHook = new ProductPricingHook([$productProxy], $salesChannelContext);

        static::assertSame([$productProxy], $productPricingHook->getProducts());
    }

    public function testGetServiceIds(): void
    {
        static::assertSame(
            [
                RepositoryFacadeHookFactory::class,
                PriceFactoryFactory::class,
                SystemConfigFacadeHookFactory::class,
                SalesChannelRepositoryFacadeHookFactory::class,
            ],
            ProductPricingHook::getServiceIds()
        );
    }

    public function testGetName(): void
    {
        $productPricingHook = new ProductPricingHook([], static::createStub(SalesChannelContext::class));

        static::assertSame('product-pricing', $productPricingHook->getName());
    }

    public function testGetSalesChannelContext(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $productPricingHook = new ProductPricingHook([], $salesChannelContext);

        static::assertSame($salesChannelContext, $productPricingHook->getSalesChannelContext());
    }
}
