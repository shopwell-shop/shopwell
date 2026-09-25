<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Page;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Storefront\Page\PageLoadedEvent;
use Shopwell\Storefront\Page\Wishlist\WishlistPageLoadedEvent;
use Shopwell\Storefront\Page\Wishlist\WishlistPageLoader;
use Shopwell\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class WishlistPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
    }

    public function testInActiveWishlist(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $this->systemConfigService->set('core.cart.wishlistEnabled', false);

        $customer = $context->getCustomer();
        static::assertInstanceOf(CustomerEntity::class, $customer);

        $this->expectExceptionObject(CustomerException::customerWishlistNotActivated());

        $this->getPageLoader()->load($request, $context, $customer);
    }

    public function testWishlistNotFound(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $this->systemConfigService->set('core.cart.wishlistEnabled', true);

        $page = $this->getPageLoader()->load($request, $context, $this->createCustomer());

        static::assertSame(0, $page->getWishlist()->getProductListing()->getTotal());
    }

    public function testItLoadsWishlistPage(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $this->systemConfigService->set('core.cart.wishlistEnabled', true);

        $product = $this->getRandomProduct($context);
        $customer = $context->getCustomer();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        $this->createCustomerWishlist($customer->getId(), $product->getId(), $context->getSalesChannelId());

        $event = null;
        $this->catchEvent(WishlistPageLoadedEvent::class, $event);
        $page = $this->getPageLoader()->load($request, $context, $customer);

        static::assertInstanceOf(PageLoadedEvent::class, $event);

        static::assertSame(1, $page->getWishlist()->getProductListing()->getTotal());
        static::assertSame($context->getContext(), $page->getWishlist()->getProductListing()->getContext());
        self::assertPageEvent(WishlistPageLoadedEvent::class, $event, $context, $request, $page);
    }

    protected function getPageLoader(): WishlistPageLoader
    {
        return static::getContainer()->get(WishlistPageLoader::class);
    }

    private function createCustomerWishlist(string $customerId, string $productId, string $salesChannelId): string
    {
        $customerWishlistId = Uuid::randomHex();
        $customerWishlistRepository = static::getContainer()->get('customer_wishlist.repository');

        $customerWishlistRepository->create([
            [
                'id' => $customerWishlistId,
                'customerId' => $customerId,
                'salesChannelId' => $salesChannelId,
                'products' => [
                    [
                        'productId' => $productId,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        return $customerWishlistId;
    }
}
