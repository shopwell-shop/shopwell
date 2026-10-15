<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\SalesChannel\LoyaltyRedeemRoute;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopwell\Storefront\Page\Account\Loyalty\AccountLoyaltyPageLoadedHook;
use Shopwell\Storefront\Page\Account\Loyalty\AccountLoyaltyPageLoader;
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
class AccountLoyaltyController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AccountLoyaltyPageLoader $pageLoader,
        private readonly LoyaltyRedeemRoute $loyaltyRedeemRoute,
    ) {
    }

    #[Route(
        path: '/account/loyalty',
        name: 'frontend.account.loyalty.page',
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

        $this->hook(new AccountLoyaltyPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/account/loyalty/index.html.twig', [
            'page' => $page,
        ]);
    }

    #[Route(
        path: '/account/loyalty/json',
        name: 'frontend.account.loyalty.json',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            'XmlHttpRequest' => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function loyaltyJson(Request $request, SalesChannelContext $context, CustomerEntity $customer): JsonResponse
    {
        $page = $this->pageLoader->load($request, $context, $customer);

        return $this->json(['success' => true, 'loyalty' => $page->getLoyalty()->all()]);
    }

    #[Route(
        path: '/account/loyalty/redeem-points',
        name: 'frontend.account.loyalty.redeem',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            'XmlHttpRequest' => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function redeemPoints(Request $request, Cart $cart, SalesChannelContext $context, CustomerEntity $customer): JsonResponse
    {
        try {
            $this->loyaltyRedeemRoute->redeem($request, $cart, $context, $customer);
        } catch (HttpException $exception) {
            return $this->json(
                ['success' => false, 'errorCode' => $exception->getErrorCode()],
                $exception->getStatusCode()
            );
        }

        return $this->json(['success' => true]);
    }
}
