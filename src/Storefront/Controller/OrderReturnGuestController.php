<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnGuestRoute;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnSettings;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopwell\Storefront\Page\Account\OrderReturn\GuestOrderReturnPage;
use Shopwell\Storefront\Page\Account\OrderReturn\GuestOrderReturnPageLoadedHook;
use Shopwell\Storefront\Page\Account\OrderReturn\GuestOrderReturnPageLoader;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 *
 * A guest proves the order once, with the order number and the mail address it was placed with. The
 * token that answers is kept in the session and never printed into a url: a link that carries it would
 * end up in a mail, a screenshot or a browser history, and it is valid for a week.
 */
#[Package('after-sales')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class OrderReturnGuestController extends StorefrontController
{
    /**
     * The proof of a guest lives in a cookie and not in a url: a link that carries it ends up in a
     * mail, a screenshot or a browser history, and it is valid for a week. HttpOnly keeps it away
     * from scripts, the path limits it to the pages that need it.
     */
    private const COOKIE_TOKEN = 'sw-order-return-guest';

    private const TOKEN_LIFETIME = 604800;

    /**
     * @internal
     */
    public function __construct(
        private readonly GuestOrderReturnPageLoader $pageLoader,
        private readonly OrderReturnGuestRoute $guestRoute,
        private readonly OrderReturnSettings $settings,
    ) {
    }

    #[Route(
        path: '/order-return',
        name: 'frontend.order-return.guest.page',
        options: ['seo' => false],
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_GET]
    )]
    public function index(Request $request, SalesChannelContext $context): Response
    {
        $this->assertEnabled($context);

        $page = $this->pageLoader->load($request, $context, $this->token($request));

        return $this->renderGuestPage(
            $page,
            $context,
            $page->isVerified()
                ? '@Storefront/storefront/page/order-return/guest-index.html.twig'
                : '@Storefront/storefront/page/order-return/guest-verify.html.twig'
        );
    }

    #[Route(
        path: '/order-return',
        name: 'frontend.order-return.guest.verify',
        options: ['seo' => false],
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_POST]
    )]
    public function verify(Request $request, SalesChannelContext $context): RedirectResponse
    {
        $this->assertEnabled($context);

        try {
            $verified = $this->guestRoute->verify($request, $context)->getObject();
        } catch (HttpException $exception) {
            return $this->fail($exception, 'frontend.order-return.guest.page');
        }

        $token = $verified->get('token');

        if (!\is_string($token) || $token === '') {
            $this->addFlash(self::DANGER, $this->trans('account.orderReturnGuestVerifyFailed'));

            return $this->redirectToRoute('frontend.order-return.guest.page');
        }

        $response = $this->redirectToRoute('frontend.order-return.guest.page');
        $response->headers->setCookie($this->tokenCookie($request, $token));

        return $response;
    }

    #[Route(
        path: '/order-return/forget',
        name: 'frontend.order-return.guest.forget',
        options: ['seo' => false],
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_POST]
    )]
    public function forget(Request $request, SalesChannelContext $context): RedirectResponse
    {
        $this->assertEnabled($context);

        $response = $this->redirectToRoute('frontend.order-return.guest.page');
        $response->headers->clearCookie(self::COOKIE_TOKEN, '/order-return');

        return $response;
    }

    #[Route(
        path: '/order-return/detail/{id}',
        name: 'frontend.order-return.guest.detail',
        options: ['seo' => false],
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_GET]
    )]
    public function detail(string $id, Request $request, SalesChannelContext $context): Response
    {
        $this->assertEnabled($context);

        $token = $this->token($request);

        if ($token === null) {
            return $this->redirectToRoute('frontend.order-return.guest.page');
        }

        try {
            $page = $this->pageLoader->loadDetail($request, $context, $token, $id);
        } catch (HttpException) {
            // the token does not cover this return - the same answer as a return that does not exist
            throw new NotFoundHttpException();
        }

        return $this->renderGuestPage($page, $context, '@Storefront/storefront/page/order-return/guest-detail.html.twig');
    }

    #[Route(
        path: '/order-return/create',
        name: 'frontend.order-return.guest.create',
        options: ['seo' => false],
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_POST]
    )]
    public function create(Request $request, SalesChannelContext $context): Response
    {
        $this->assertEnabled($context);

        $token = $this->token($request);

        if ($token === null) {
            return $this->redirectToRoute('frontend.order-return.guest.page');
        }

        $created = $this->guestRoute->create(
            $this->createRequest($request, $token),
            $context
        )->getObject();

        $presented = $created->get('orderReturn');
        $id = \is_array($presented) && \is_string($presented['id'] ?? null) ? $presented['id'] : null;

        $this->addFlash(self::SUCCESS, $this->trans('account.orderReturnCreated'));

        if ($id === null) {
            return $this->redirectToRoute('frontend.order-return.guest.page');
        }

        return $this->redirectToRoute('frontend.order-return.guest.detail', ['id' => $id]);
    }

    #[Route(
        path: '/order-return/{id}/cancel',
        name: 'frontend.order-return.guest.cancel',
        options: ['seo' => false],
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_POST]
    )]
    public function cancel(string $id, Request $request, SalesChannelContext $context): RedirectResponse
    {
        return $this->act($id, $request, $context, 'cancel', 'account.orderReturnCanceled');
    }

    #[Route(
        path: '/order-return/{id}/ship',
        name: 'frontend.order-return.guest.ship',
        options: ['seo' => false],
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_POST]
    )]
    public function ship(string $id, Request $request, SalesChannelContext $context): RedirectResponse
    {
        return $this->act($id, $request, $context, 'ship', 'account.orderReturnShipped');
    }

    #[Route(
        path: '/order-return/{id}/message',
        name: 'frontend.order-return.guest.message',
        options: ['seo' => false],
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_POST]
    )]
    public function message(string $id, Request $request, SalesChannelContext $context): RedirectResponse
    {
        return $this->act($id, $request, $context, 'message', 'account.orderReturnMessageSent');
    }

    /**
     * The three actions of a guest share one shape: carry the token, run, answer with the detail page.
     *
     * @param 'cancel'|'ship'|'message' $action
     */
    private function act(string $id, Request $request, SalesChannelContext $context, string $action, string $successSnippet): RedirectResponse
    {
        $this->assertEnabled($context);

        $token = $this->token($request);

        if ($token === null) {
            return $this->redirectToRoute('frontend.order-return.guest.page');
        }

        try {
            $guestRequest = $this->requestWithToken($request, $token);

            switch ($action) {
                case 'cancel':
                    $this->guestRoute->cancel($id, $guestRequest, $context);

                    break;
                case 'ship':
                    $this->guestRoute->ship($id, $guestRequest, $context);

                    break;
                case 'message':
                    $this->guestRoute->message($id, $guestRequest, $context);

                    break;
            }
        } catch (HttpException $exception) {
            return $this->fail($exception, 'frontend.order-return.guest.detail', ['id' => $id]);
        }

        $this->addFlash(self::SUCCESS, $this->trans($successSnippet));

        return $this->redirectToRoute('frontend.order-return.guest.detail', ['id' => $id]);
    }

    /**
     * The html form of a guest posts flat quantities; the api takes a list of positions.
     */
    private function createRequest(Request $request, string $token): Request
    {
        $positions = [];

        /** @var array<string, mixed> $quantities */
        $quantities = $request->request->all('positions');

        foreach ($quantities as $lineItemId => $quantity) {
            $positions[] = [
                'lineItemId' => (string) $lineItemId,
                'quantity' => (int) $quantity,
            ];
        }

        return new Request([], [
            'token' => $token,
            'type' => (string) $request->request->get('type', ''),
            'positions' => $positions,
            'reasonId' => $request->request->get('reasonId'),
            'reasonText' => $request->request->get('reasonText'),
            'customerNote' => $request->request->get('customerNote'),
            'evidence' => $this->evidenceLines($request),
        ]);
    }

    /**
     * The textarea holds one link per line, the api takes a list.
     *
     * @return list<string>
     */
    private function evidenceLines(Request $request): array
    {
        $raw = (string) $request->request->get('evidence', '');

        $lines = array_map('trim', preg_split('/\R/', $raw) ?: []);

        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }

    private function requestWithToken(Request $request, string $token): Request
    {
        return $request->duplicate(['token' => $token]);
    }

    private function token(Request $request): ?string
    {
        $token = $request->cookies->get(self::COOKIE_TOKEN);

        return \is_string($token) && $token !== '' ? $token : null;
    }

    private function tokenCookie(Request $request, string $token): Cookie
    {
        return Cookie::create(
            self::COOKIE_TOKEN,
            $token,
            time() + self::TOKEN_LIFETIME,
            '/order-return',
            null,
            $request->isSecure(),
            true,
            false,
            Cookie::SAMESITE_LAX,
        );
    }

    private function renderGuestPage(GuestOrderReturnPage $page, SalesChannelContext $context, string $template): Response
    {
        $this->hook(new GuestOrderReturnPageLoadedHook($page, $context));

        return $this->renderStorefront($template, [
            'page' => $page,
        ]);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function fail(HttpException $exception, string $route, array $parameters = []): RedirectResponse
    {
        // a guest gets one sentence: whether the order exists is not something an anonymous visitor is
        // allowed to learn from a difference in wording
        $snippet = $exception->getErrorCode() === OrderReturnException::RETURN_ORDER_NOT_FOUND_CODE
            ? 'account.orderReturnGuestVerifyFailed'
            : 'account.orderReturnErrorGeneric';

        $this->addFlash(self::DANGER, $this->trans($snippet));

        return $this->redirectToRoute($route, $parameters);
    }

    private function assertEnabled(SalesChannelContext $context): void
    {
        if (!$this->settings->isEnabled($context->getSalesChannelId()) || !$this->settings->allowGuest($context->getSalesChannelId())) {
            throw new NotFoundHttpException();
        }
    }
}
