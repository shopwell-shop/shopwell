<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletPaymentRoute;
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
}
