<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Page\Account;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopwell\Storefront\Page\Account\Order\AccountOrderDetailPageLoadedEvent;
use Shopwell\Storefront\Page\Account\Order\AccountOrderDetailPageLoader;
use Shopwell\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - will be removed
 */
#[Package('discovery')]
class OrderDetailPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);
    }

    public function testItLoadsOrders(): void
    {
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $orderId = $this->placeRandomOrder($context);

        $request = new Request();
        $request->query->set('id', $orderId);

        $accountOrderDetailEvent = null;
        $this->catchEvent(AccountOrderDetailPageLoadedEvent::class, $accountOrderDetailEvent);

        $orderRequestEvent = null;
        $this->catchEvent(OrderRouteRequestEvent::class, $orderRequestEvent);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame($orderId, $page->getOrder()->getId());
        self::assertPageEvent(AccountOrderDetailPageLoadedEvent::class, $accountOrderDetailEvent, $context, $request, $page);

        static::assertInstanceOf(OrderRouteRequestEvent::class, $orderRequestEvent);
        static::assertSame($request, $orderRequestEvent->getStorefrontRequest());
        static::assertSame($context, $orderRequestEvent->getSalesChannelContext());
        static::assertSame($context->getContext(), $orderRequestEvent->getContext());
    }

    public function testMissingOrderIdThrowsException(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $this->expectException(RoutingException::class);
        $this->getPageLoader()->load($request, $context);
    }

    public function testUnknownOrderThrowsNotFoundHttpException(): void
    {
        $request = new Request();
        $request->query->set('id', Uuid::randomHex());
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        $this->expectException(NotFoundHttpException::class);
        $this->getPageLoader()->load($request, $context);
    }

    protected function getPageLoader(): AccountOrderDetailPageLoader
    {
        return static::getContainer()->get(AccountOrderDetailPageLoader::class);
    }
}
