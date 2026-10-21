<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnRoute;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnRouteResponse;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnSettings;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Shopwell\Storefront\Controller\AccountOrderReturnController;
use Shopwell\Storefront\Page\Account\OrderReturn\AccountOrderReturnPage;
use Shopwell\Storefront\Page\Account\OrderReturn\AccountOrderReturnPageLoadedHook;
use Shopwell\Storefront\Page\Account\OrderReturn\AccountOrderReturnPageLoader;
use Shopwell\Tests\Unit\Storefront\Controller\Stub\AccountOrderReturnControllerStub;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The forms of "my returns" are plain html forms: they post, the controller answers with a redirect
 * and a flash. What a customer is told per failure is the interesting part - a technical error code is
 * never printed at a customer.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(AccountOrderReturnController::class)]
class AccountOrderReturnControllerTest extends TestCase
{
    public function testTheListRendersThePageAndAnnouncesIt(): void
    {
        $page = new AccountOrderReturnPage();

        $pageLoader = static::createStub(AccountOrderReturnPageLoader::class);
        $pageLoader->method('loadList')->willReturn($page);

        $controller = $this->controller(pageLoader: $pageLoader);

        $response = $controller->index(new Request(), $this->context(), $this->customer());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('@Storefront/storefront/page/account/order-return/index.html.twig', $controller->recorder()->renderStorefrontView);
        static::assertSame($page, $controller->recorder()->renderStorefrontParameters['page']);
        static::assertInstanceOf(AccountOrderReturnPageLoadedHook::class, $controller->recorder()->calledHook);
    }

    public function testAShopThatSwitchedReturnsOffHasNoReturnPages(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $controller = $this->controller(settings: $this->settings(enabled: false));

        $controller->index(new Request(), $this->context(), $this->customer());
    }

    public function testTheFormOfABrowserIsTranslatedIntoTheApiPayload(): void
    {
        $route = static::createStub(OrderReturnRoute::class);
        $route->method('create')->willReturn($this->response(['id' => 'return-id']));

        $request = new Request(request: [
            'type' => 'return_refund',
            'positions' => ['line-item-id' => '2'],
            'reasonId' => 'reason-id',
            'reasonText' => '',
            'customerNote' => 'the box was empty',
            'evidence' => "https://example.com/one.jpg\n\n https://example.com/two.jpg ",
        ]);

        $response = $this->controller(orderReturnRoute: $route)->submitCreate('order-id', $request, $this->context(), $this->customer());

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('frontend.account.order-return.detail', $this->targetUrl($response));
    }

    public function testThePayloadOfTheFormIsWhatTheApiExpects(): void
    {
        $route = $this->createMock(OrderReturnRoute::class);
        $route->expects($this->once())
            ->method('create')
            ->with(static::callback(static fn (Request $request): bool => $request->request->all() === [
                'orderId' => 'order-id',
                'type' => 'refund_only',
                'positions' => [['lineItemId' => 'line-item-id', 'quantity' => 2]],
                'reasonId' => 'reason-id',
                'reasonText' => null,
                'customerNote' => null,
                'evidence' => ['https://example.com/one.jpg', 'https://example.com/two.jpg'],
            ]))
            ->willReturn($this->response(['id' => 'return-id']));

        $request = new Request(request: [
            'type' => 'refund_only',
            'positions' => ['line-item-id' => '2'],
            'reasonId' => 'reason-id',
            'evidence' => "https://example.com/one.jpg\nhttps://example.com/two.jpg",
        ]);

        $this->controller(orderReturnRoute: $route)->submitCreate('order-id', $request, $this->context(), $this->customer());
    }

    public function testACreationWithoutAnIdLeadsBackToTheList(): void
    {
        $route = static::createStub(OrderReturnRoute::class);
        $route->method('create')->willReturn($this->response([]));

        $response = $this->controller(orderReturnRoute: $route)->submitCreate('order-id', new Request(), $this->context(), $this->customer());

        static::assertSame('frontend.account.order-return.page', $this->targetUrl($response));
    }

    public function testARejectedCreationExplainsTheReasonAndKeepsTheForm(): void
    {
        $route = static::createStub(OrderReturnRoute::class);
        $route->method('create')->willThrowException(OrderReturnException::windowExpired('SW-10001', 30));

        $controller = $this->controller(orderReturnRoute: $route);

        $response = $controller->submitCreate('order-id', new Request(), $this->context(), $this->customer());

        static::assertSame('frontend.account.order-return.create', $this->targetUrl($response));
        static::assertSame(['danger' => ['account.orderReturnErrorWindowExpired']], $controller->recorder()->flashBag);
    }

    public function testAnErrorWithoutOwnWordingFallsBackToTheGenericMessage(): void
    {
        $route = static::createStub(OrderReturnRoute::class);
        $route->method('create')->willThrowException(OrderReturnException::refundNotPossible('RT-1', 'nope'));

        $controller = $this->controller(orderReturnRoute: $route);

        $controller->submitCreate('order-id', new Request(), $this->context(), $this->customer());

        static::assertSame(['danger' => ['account.orderReturnErrorGeneric']], $controller->recorder()->flashBag);
    }

    public function testACancelIsAnnouncedAndLeadsToTheDetail(): void
    {
        $route = static::createStub(OrderReturnRoute::class);

        $controller = $this->controller(orderReturnRoute: $route);

        $response = $controller->cancel('return-id', new Request(), $this->context(), $this->customer());

        static::assertSame('frontend.account.order-return.detail', $this->targetUrl($response));
        static::assertSame(['success' => ['account.orderReturnCanceled']], $controller->recorder()->flashBag);
    }

    public function testAFailedCancelKeepsTheCustomerOnTheDetail(): void
    {
        $route = static::createStub(OrderReturnRoute::class);
        $route->method('cancel')->willThrowException(OrderReturnException::stateNotAllowed('closed', 'cancel'));

        $controller = $this->controller(orderReturnRoute: $route);

        $controller->cancel('return-id', new Request(), $this->context(), $this->customer());

        static::assertSame(['danger' => ['account.orderReturnErrorState']], $controller->recorder()->flashBag);
    }

    public function testAShipmentAndAMessageAnnounceThemselves(): void
    {
        $controller = $this->controller();

        $ship = $controller->ship('return-id', new Request(), $this->context(), $this->customer());
        $message = $controller->message('return-id', new Request(), $this->context(), $this->customer());

        static::assertSame('frontend.account.order-return.detail', $this->targetUrl($ship));
        static::assertSame('frontend.account.order-return.detail', $this->targetUrl($message));
        static::assertSame(
            ['account.orderReturnShipped', 'account.orderReturnMessageSent'],
            $controller->recorder()->flashBag['success']
        );
    }

    public function testTheDetailAndTheFormAreRendered(): void
    {
        $pageLoader = static::createStub(AccountOrderReturnPageLoader::class);
        $pageLoader->method('loadDetail')->willReturn(new AccountOrderReturnPage());
        $pageLoader->method('loadCreate')->willReturn(new AccountOrderReturnPage());

        $controller = $this->controller(pageLoader: $pageLoader);

        $controller->detail('return-id', new Request(), $this->context(), $this->customer());
        static::assertSame('@Storefront/storefront/page/account/order-return/detail.html.twig', $controller->recorder()->renderStorefrontView);

        $controller->showCreateForm('order-id', new Request(), $this->context(), $this->customer());
        static::assertSame('@Storefront/storefront/page/account/order-return/create.html.twig', $controller->recorder()->renderStorefrontView);
    }

    private function targetUrl(Response $response): string
    {
        static::assertInstanceOf(RedirectResponse::class, $response);

        return $response->getTargetUrl();
    }

    private function controller(
        ?AccountOrderReturnPageLoader $pageLoader = null,
        ?OrderReturnRoute $orderReturnRoute = null,
        ?OrderReturnSettings $settings = null,
    ): AccountOrderReturnControllerStub {
        return new AccountOrderReturnControllerStub(
            $pageLoader ?? static::createStub(AccountOrderReturnPageLoader::class),
            $orderReturnRoute ?? static::createStub(OrderReturnRoute::class),
            $settings ?? $this->settings(),
        );
    }

    private function settings(bool $enabled = true): OrderReturnSettings
    {
        $settings = $this->createMock(OrderReturnSettings::class);
        $settings->method('isEnabled')->willReturn($enabled);

        return $settings;
    }

    private function customer(): CustomerEntity
    {
        return (new CustomerEntity())->assign(['id' => 'customer-id']);
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
