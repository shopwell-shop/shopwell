<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Checkout\OrderReturn\SalesChannel\OrderReturnRoute;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnSettings;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopwell\Storefront\Page\Account\OrderReturn\AccountOrderReturnPageLoadedHook;
use Shopwell\Storefront\Page\Account\OrderReturn\AccountOrderReturnPageLoader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 *
 * The forms of this controller are plain HTML forms: they post, the controller answers with a redirect
 * and a flash message. Nothing here needs javascript, which is also why there is no json action - the
 * store-api already speaks json for a client that wants it.
 */
#[Package('after-sales')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class AccountOrderReturnController extends StorefrontController
{
    /**
     * What the customer is told per failure. A code the shop has no text for falls back to the generic
     * message instead of printing a technical error code at a customer.
     */
    private const ERROR_SNIPPETS = [
        OrderReturnException::RETURN_WINDOW_EXPIRED_CODE => 'account.orderReturnErrorWindowExpired',
        OrderReturnException::RETURN_ORDER_NOT_FINISHED_CODE => 'account.orderReturnErrorOrderNotFinished',
        OrderReturnException::RETURN_QUANTITY_EXCEEDED_CODE => 'account.orderReturnErrorQuantity',
        OrderReturnException::RETURN_EMPTY_POSITIONS_CODE => 'account.orderReturnErrorEmptyPositions',
        OrderReturnException::RETURN_REASON_UNKNOWN_CODE => 'account.orderReturnErrorReason',
        OrderReturnException::RETURN_MISSING_TRACKING_CODE_CODE => 'account.orderReturnErrorTracking',
        OrderReturnException::RETURN_EMPTY_MESSAGE_CODE => 'account.orderReturnErrorMessage',
        OrderReturnException::RETURN_STATE_NOT_ALLOWED_CODE => 'account.orderReturnErrorState',
        OrderReturnException::RETURN_DISABLED_CODE => 'account.orderReturnErrorDisabled',
    ];

    private const ERROR_FALLBACK_SNIPPET = 'account.orderReturnErrorGeneric';

    /**
     * @internal
     */
    public function __construct(
        private readonly AccountOrderReturnPageLoader $pageLoader,
        private readonly OrderReturnRoute $orderReturnRoute,
        private readonly OrderReturnSettings $settings,
    ) {
    }

    #[Route(
        path: '/account/order-return',
        name: 'frontend.account.order-return.page',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function index(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $this->assertEnabled($context);

        $page = $this->pageLoader->loadList($request, $context, $customer);

        $this->hook(new AccountOrderReturnPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/account/order-return/index.html.twig', [
            'page' => $page,
        ]);
    }

    #[Route(
        path: '/account/order-return/detail/{id}',
        name: 'frontend.account.order-return.detail',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function detail(string $id, Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $this->assertEnabled($context);

        $page = $this->pageLoader->loadDetail($request, $context, $customer, $id);

        $this->hook(new AccountOrderReturnPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/account/order-return/detail.html.twig', [
            'page' => $page,
        ]);
    }

    #[Route(
        path: '/account/order-return/create/{orderId}',
        name: 'frontend.account.order-return.create',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function showCreateForm(string $orderId, Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $this->assertEnabled($context);

        $page = $this->pageLoader->loadCreate($request, $context, $customer, $orderId);

        $this->hook(new AccountOrderReturnPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/account/order-return/create.html.twig', [
            'page' => $page,
        ]);
    }

    #[Route(
        path: '/account/order-return/create/{orderId}',
        name: 'frontend.account.order-return.create.submit',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function submitCreate(string $orderId, Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $this->assertEnabled($context);

        try {
            $created = $this->orderReturnRoute->create($this->createRequest($request, $orderId), $context, $customer);
        } catch (HttpException $exception) {
            return $this->fail(
                $exception,
                'frontend.account.order-return.create',
                ['orderId' => $orderId]
            );
        }

        $this->addFlash(self::SUCCESS, $this->trans('account.orderReturnCreated'));

        $presented = $created->getObject()->get('orderReturn');
        $id = \is_array($presented) && \is_string($presented['id'] ?? null) ? $presented['id'] : null;

        return $this->redirectToDetail($id);
    }

    #[Route(
        path: '/account/order-return/{id}/cancel',
        name: 'frontend.account.order-return.cancel',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function cancel(string $id, Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $this->assertEnabled($context);

        try {
            $this->orderReturnRoute->cancel($request, $id, $context, $customer);
        } catch (HttpException $exception) {
            return $this->fail($exception, 'frontend.account.order-return.detail', ['id' => $id]);
        }

        $this->addFlash(self::SUCCESS, $this->trans('account.orderReturnCanceled'));

        return $this->redirectToDetail($id);
    }

    #[Route(
        path: '/account/order-return/{id}/ship',
        name: 'frontend.account.order-return.ship',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function ship(string $id, Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $this->assertEnabled($context);

        try {
            $this->orderReturnRoute->ship($request, $id, $context, $customer);
        } catch (HttpException $exception) {
            return $this->fail($exception, 'frontend.account.order-return.detail', ['id' => $id]);
        }

        $this->addFlash(self::SUCCESS, $this->trans('account.orderReturnShipped'));

        return $this->redirectToDetail($id);
    }

    #[Route(
        path: '/account/order-return/{id}/message',
        name: 'frontend.account.order-return.message',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function message(string $id, Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $this->assertEnabled($context);

        try {
            $this->orderReturnRoute->message($request, $id, $context, $customer);
        } catch (HttpException $exception) {
            return $this->fail($exception, 'frontend.account.order-return.detail', ['id' => $id]);
        }

        $this->addFlash(self::SUCCESS, $this->trans('account.orderReturnMessageSent'));

        return $this->redirectToDetail($id);
    }

    /**
     * The html form names its fields differently from the api on purpose: a browser posts a flat list
     * of quantities keyed by line item, the api takes a list of positions. Translating between the two
     * is the job of this action, not of the template.
     */
    private function createRequest(Request $request, string $orderId): Request
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

        // the api request is a copy of the original one: a fresh Request would lose the attributes
        // and the context the kernel put on it
        $apiRequest = $request->duplicate();
        $apiRequest->request->replace([
            'orderId' => $orderId,
            'type' => (string) $request->request->get('type', ''),
            'positions' => $positions,
            'reasonId' => $request->request->get('reasonId'),
            'reasonText' => $request->request->get('reasonText'),
            'customerNote' => $request->request->get('customerNote'),
            'evidence' => $this->evidenceLines($request),
        ]);

        return $apiRequest;
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

    /**
     * A return that was just created is shown, not listed: the customer wants to see whether the
     * request arrived, and the list is one click away.
     */
    private function redirectToDetail(?string $id): RedirectResponse
    {
        if ($id === null || $id === '') {
            return $this->redirectToRoute('frontend.account.order-return.page');
        }

        return $this->redirectToRoute('frontend.account.order-return.detail', ['id' => $id]);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function fail(HttpException $exception, string $route, array $parameters): RedirectResponse
    {
        $snippet = self::ERROR_SNIPPETS[$exception->getErrorCode()] ?? self::ERROR_FALLBACK_SNIPPET;

        $this->addFlash(self::DANGER, $this->trans($snippet));

        return $this->redirectToRoute($route, $parameters);
    }

    /**
     * A shop that switched returns off has no return pages either - an existing link answers like any
     * other page that does not exist instead of opening a feature that is not there.
     */
    private function assertEnabled(SalesChannelContext $context): void
    {
        if (!$this->settings->isEnabled($context->getSalesChannelId())) {
            throw new NotFoundHttpException();
        }
    }
}
