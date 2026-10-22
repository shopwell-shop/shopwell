<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletPaymentRoute;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletTopUpCreateRoute;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletTopUpStatusRoute;
use Shopwell\Core\Checkout\Wallet\Service\WalletSettings;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopwell\Storefront\Page\Account\Wallet\AccountWalletPageLoadedHook;
use Shopwell\Storefront\Page\Account\Wallet\AccountWalletPageLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class AccountWalletController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AccountWalletPageLoader $pageLoader,
        private readonly WalletPaymentRoute $walletPaymentRoute,
        private readonly WalletTopUpCreateRoute $topUpCreateRoute,
        private readonly WalletTopUpStatusRoute $topUpStatusRoute,
        private readonly WalletSettings $settings,
    ) {
    }

    #[Route(
        path: '/account/wallet',
        name: 'frontend.account.wallet.page',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function index(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $this->assertWalletEnabled($context);

        $page = $this->pageLoader->load($request, $context, $customer);

        $this->hook(new AccountWalletPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/account/wallet/index.html.twig', [
            'page' => $page,
        ]);
    }

    #[Route(
        path: '/account/wallet/json',
        name: 'frontend.account.wallet.json',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            'XmlHttpRequest' => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function walletJson(Request $request, SalesChannelContext $context, CustomerEntity $customer): JsonResponse
    {
        $this->assertWalletEnabled($context);

        $page = $this->pageLoader->load($request, $context, $customer);

        return $this->json(['success' => true, 'wallet' => $page->getWallet()->all()]);
    }

    #[Route(
        path: '/account/wallet/payment',
        name: 'frontend.account.wallet.payment',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            'XmlHttpRequest' => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function pay(Request $request, Cart $cart, SalesChannelContext $context, CustomerEntity $customer): JsonResponse
    {
        try {
            $this->walletPaymentRoute->pay($request, $cart, $context, $customer);
        } catch (HttpException $exception) {
            return $this->json(
                ['success' => false, 'errorCode' => $exception->getErrorCode()],
                $exception->getStatusCode()
            );
        }

        return $this->json(['success' => true]);
    }

    /**
     * Opens a recharge for the customer and answers what the browser has to do next.
     *
     * The answer is passed through unchanged: a redirect channel hands out a url, a channel that is
     * scanned hands out the content of the qr code. The page decides nothing about the payment, it only
     * shows what the channel asked for - which is why a channel the shop did not enable for the
     * platform is rejected in the route and never reaches this action as a half started payment.
     */
    #[Route(
        path: '/account/wallet/top-up',
        name: 'frontend.account.wallet.top-up',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            'XmlHttpRequest' => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function topUp(Request $request, SalesChannelContext $context, CustomerEntity $customer): JsonResponse
    {
        try {
            $response = $this->topUpCreateRoute->create($request, $context, $customer);
        } catch (HttpException $exception) {
            return $this->json(
                ['success' => false, 'errorCode' => $exception->getErrorCode()],
                $exception->getStatusCode()
            );
        }

        return $this->json(['success' => true, 'topUp' => $response->getObject()->all()]);
    }

    /**
     * The state of one recharge of this customer, polled after a qr code was scanned.
     *
     * A channel that is scanned reports back on its own, so the page cannot know when the money
     * arrived. Asking the top-up - and not the gateway - keeps the page honest: the balance changed
     * exactly when this answers `credited`.
     */
    #[Route(
        path: '/account/wallet/top-up/{topUpId}',
        name: 'frontend.account.wallet.top-up.status',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            'XmlHttpRequest' => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function topUpStatus(string $topUpId, SalesChannelContext $context, CustomerEntity $customer): JsonResponse
    {
        try {
            $response = $this->topUpStatusRoute->load($topUpId, $context, $customer);
        } catch (HttpException $exception) {
            return $this->json(
                ['success' => false, 'errorCode' => $exception->getErrorCode()],
                $exception->getStatusCode()
            );
        }

        return $this->json(['success' => true, 'topUp' => $response->getObject()->all()]);
    }

    /**
     * A shop that switched the wallet off has no wallet page either - an existing link or bookmark
     * answers like any other page that does not exist instead of opening a page without a feature.
     */
    private function assertWalletEnabled(SalesChannelContext $context): void
    {
        if (!$this->settings->isEnabled($context->getSalesChannelId())) {
            throw new NotFoundHttpException();
        }
    }
}
