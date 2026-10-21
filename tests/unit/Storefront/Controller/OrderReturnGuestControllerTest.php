<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnGuestRoute;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnRouteResponse;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnSettings;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Controller\OrderReturnGuestController;
use Shopwell\Storefront\Page\Account\OrderReturn\GuestOrderReturnPage;
use Shopwell\Storefront\Page\Account\OrderReturn\GuestOrderReturnPageLoader;
use Shopwell\Tests\Unit\Storefront\Controller\Stub\OrderReturnGuestControllerStub;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The proof of a guest lives in a cookie and never in a url: a link that carries it ends up in a mail,
 * a screenshot or the browser history of somebody else.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnGuestController::class)]
class OrderReturnGuestControllerTest extends TestCase
{
    private const COOKIE = 'sw-order-return-guest';

    public function testWithoutATokenTheGuestSeesTheForm(): void
    {
        $controller = $this->controller();

        $controller->index(new Request(), $this->context());

        static::assertSame('@Storefront/storefront/page/order-return/guest-verify.html.twig', $controller->recorder()->renderStorefrontView);
    }

    public function testWithATokenTheGuestSeesTheReturns(): void
    {
        $pageLoader = $this->createMock(GuestOrderReturnPageLoader::class);
        $pageLoader->expects($this->once())
            ->method('load')
            ->with(static::isInstanceOf(Request::class), static::anything(), 'the-token')
            ->willReturn((new GuestOrderReturnPage())->assign(['verified' => true]));

        $controller = $this->controller(pageLoader: $pageLoader);

        $controller->index($this->requestWithToken(), $this->context());

        static::assertSame('@Storefront/storefront/page/order-return/guest-index.html.twig', $controller->recorder()->renderStorefrontView);
    }

    public function testAChannelThatDoesNotOfferGuestReturnsHasNoGuestPages(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller(settings: $this->settings(allowGuest: false))->index(new Request(), $this->context());
    }

    public function testAProvedOrderIsKeptInAnHttpOnlyCookie(): void
    {
        $route = static::createStub(OrderReturnGuestRoute::class);
        $route->method('verify')->willReturn(new OrderReturnRouteResponse(new ArrayStruct(['token' => 'a-signed-token'])));

        $controller = $this->controller(guestRoute: $route);

        $response = $controller->verify(new Request(), $this->context());

        static::assertSame('frontend.order-return.guest.page', $this->targetUrl($response));

        $cookies = $response->headers->getCookies();
        static::assertCount(1, $cookies);
        static::assertSame(self::COOKIE, $cookies[0]->getName());
        static::assertSame('a-signed-token', $cookies[0]->getValue());
        static::assertTrue($cookies[0]->isHttpOnly());
        static::assertSame('/order-return', $cookies[0]->getPath());
    }

    public function testARefusedProofKeepsTheFormWithoutACookie(): void
    {
        $route = static::createStub(OrderReturnGuestRoute::class);
        $route->method('verify')->willThrowException(OrderReturnException::orderNotFound('SW-10001'));

        $controller = $this->controller(guestRoute: $route);

        $response = $controller->verify(new Request(), $this->context());

        static::assertSame('frontend.order-return.guest.page', $this->targetUrl($response));
        static::assertSame([], $response->headers->getCookies());
        static::assertSame(['danger' => ['account.orderReturnGuestVerifyFailed']], $controller->recorder()->flashBag);
    }

    public function testAVerificationWithoutATokenIsNotAnErrorTheGuestCanNotActOn(): void
    {
        $route = static::createStub(OrderReturnGuestRoute::class);
        $route->method('verify')->willReturn($this->response([]));

        $controller = $this->controller(guestRoute: $route);

        $response = $controller->verify(new Request(), $this->context());

        static::assertSame('frontend.order-return.guest.page', $this->targetUrl($response));
        static::assertSame(['danger' => ['account.orderReturnGuestVerifyFailed']], $controller->recorder()->flashBag);
    }

    public function testForgettingTheTokenClearsTheCookie(): void
    {
        $response = $this->controller()->forget($this->requestWithToken(), $this->context());

        $cookies = $response->headers->getCookies();
        static::assertCount(1, $cookies);
        static::assertSame(self::COOKIE, $cookies[0]->getName());
        // a cleared cookie is sent with an expiration in the past and no value
        static::assertNull($cookies[0]->getValue());
        static::assertTrue($cookies[0]->isCleared());
    }

    public function testTheDetailNeedsTheToken(): void
    {
        $response = $this->controller()->detail('return-id', new Request(), $this->context());

        static::assertSame('frontend.order-return.guest.page', $this->targetUrl($response));
    }

    public function testTheDetailOfAProvedReturnIsRendered(): void
    {
        $pageLoader = static::createStub(GuestOrderReturnPageLoader::class);
        $pageLoader->method('loadDetail')->willReturn((new GuestOrderReturnPage())->assign(['verified' => true]));

        $controller = $this->controller(pageLoader: $pageLoader);

        $controller->detail('return-id', $this->requestWithToken(), $this->context());

        static::assertSame('@Storefront/storefront/page/order-return/guest-detail.html.twig', $controller->recorder()->renderStorefrontView);
    }

    public function testAReturnTheTokenDoesNotCoverIsNotFound(): void
    {
        $pageLoader = static::createStub(GuestOrderReturnPageLoader::class);
        $pageLoader->method('loadDetail')->willThrowException(OrderReturnException::guestAccessDenied());

        $this->expectException(NotFoundHttpException::class);

        $this->controller(pageLoader: $pageLoader)->detail('return-id', $this->requestWithToken(), $this->context());
    }

    public function testACreationNeedsTheTokenAndLeadsToTheNewReturn(): void
    {
        $route = $this->createMock(OrderReturnGuestRoute::class);
        $route->expects($this->once())
            ->method('create')
            ->with(static::callback(static fn (Request $request): bool => $request->request->get('token') === 'the-token'
                && $request->request->all('positions') === [['lineItemId' => 'line-item-id', 'quantity' => 2]]))
            ->willReturn($this->response(['id' => 'return-id']));

        $controller = $this->controller(guestRoute: $route);

        $response = $controller->create(
            new Request(request: ['positions' => ['line-item-id' => '2'], 'type' => 'refund_only']),
            $this->context()
        );

        static::assertSame('frontend.order-return.guest.page', $this->targetUrl($response));

        $this->recorderReset($controller);

        $provedRequest = new Request(request: ['positions' => ['line-item-id' => '2']], cookies: [self::COOKIE => 'the-token']);
        $response = $controller->create($provedRequest, $this->context());

        static::assertSame('frontend.order-return.guest.detail', $this->targetUrl($response));
    }

    public function testTheActionsOfAGuestCarryTheTokenAndAnnounceThemselves(): void
    {
        $route = $this->createMock(OrderReturnGuestRoute::class);
        $route->expects($this->once())
            ->method('cancel')
            ->with(static::callback(static fn (Request $request): bool => $request->query->get('token') === 'the-token'));

        $request = new Request(cookies: [self::COOKIE => 'the-token']);
        $request->query->set('token', 'the-token');

        $controller = $this->controller(guestRoute: $route);

        $response = $controller->cancel('return-id', $request, $this->context());

        static::assertSame('frontend.order-return.guest.detail', $this->targetUrl($response));
        static::assertSame(['success' => ['account.orderReturnCanceled']], $controller->recorder()->flashBag);
    }

    public function testAFailedActionOfAGuestIsNotExplainedInDetail(): void
    {
        $route = static::createStub(OrderReturnGuestRoute::class);
        $route->method('cancel')->willThrowException(OrderReturnException::guestAccessDenied());

        $controller = $this->controller(guestRoute: $route);

        $controller->cancel('return-id', $this->requestWithToken(), $this->context());

        static::assertSame(['danger' => ['account.orderReturnErrorGeneric']], $controller->recorder()->flashBag);
    }

    private function targetUrl(Response $response): string
    {
        static::assertInstanceOf(RedirectResponse::class, $response);

        return $response->getTargetUrl();
    }

    private function controller(
        ?GuestOrderReturnPageLoader $pageLoader = null,
        ?OrderReturnGuestRoute $guestRoute = null,
        ?OrderReturnSettings $settings = null,
    ): OrderReturnGuestControllerStub {
        return new OrderReturnGuestControllerStub(
            $pageLoader ?? static::createStub(GuestOrderReturnPageLoader::class),
            $guestRoute ?? static::createStub(OrderReturnGuestRoute::class),
            $settings ?? $this->settings(),
            new MockClock('2026-10-21 09:30:15'),
        );
    }

    private function recorderReset(OrderReturnGuestControllerStub $controller): void
    {
        $controller->recorder()->reset();
    }

    private function settings(bool $allowGuest = true): OrderReturnSettings
    {
        $settings = $this->createMock(OrderReturnSettings::class);
        $settings->method('isEnabled')->willReturn(true);
        $settings->method('allowGuest')->willReturn($allowGuest);

        return $settings;
    }

    private function requestWithToken(): Request
    {
        return new Request(cookies: [self::COOKIE => 'the-token']);
    }

    private function context(): SalesChannelContext
    {
        return Generator::generateSalesChannelContext();
    }

    /**
     * @param array<string, mixed> $orderReturn
     */
    private function response(array $orderReturn): OrderReturnRouteResponse
    {
        return new OrderReturnRouteResponse(new ArrayStruct(['orderReturn' => $orderReturn]));
    }
}
