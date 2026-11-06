<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\SalesChannel\LoyaltyRedeemRoute;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopwell\Storefront\Page\Account\Rewards\AccountRewardsPageLoadedHook;
use Shopwell\Storefront\Page\Account\Rewards\AccountRewardsPageLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The rewards page: what the customer earned, and how they turn it into money off.
 *
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class AccountRewardsController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AccountRewardsPageLoader $pageLoader,
        private readonly LoyaltyRedeemRoute $loyaltyRedeemRoute,
        private readonly LoyaltySettings $settings,
    ) {
    }

    #[Route(
        path: '/account/rewards',
        name: 'frontend.account.rewards.page',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function index(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $this->assertRewardsEnabled($context);

        $page = $this->pageLoader->load($request, $context, $customer);

        $this->hook(new AccountRewardsPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/account/rewards/index.html.twig', [
            'page' => $page,
        ]);
    }

    /**
     * Where the loyalty programme used to live. A shop that switched the programme off has no rewards
     * page either, so the old address answers with the same 404 instead of forwarding to one.
     */
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
    public function legacyIndex(SalesChannelContext $context): Response
    {
        $this->assertRewardsEnabled($context);

        return $this->redirectToRoute('frontend.account.rewards.page', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * The current snapshot, for a page that updates itself after a redemption.
     */
    #[Route(
        path: '/account/rewards/detail',
        name: 'frontend.account.rewards.detail',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            'XmlHttpRequest' => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function detail(Request $request, SalesChannelContext $context, CustomerEntity $customer): JsonResponse
    {
        $this->assertRewardsEnabled($context);

        $page = $this->pageLoader->load($request, $context, $customer);

        return $this->json(['success' => true, 'rewards' => $page->getRewards()->all()]);
    }

    #[Route(
        path: '/account/rewards/redeem-points',
        name: 'frontend.account.rewards.redeem',
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

    /**
     * A shop that switched the programme off has no rewards page - an existing link or bookmark answers
     * like any other page that does not exist.
     */
    private function assertRewardsEnabled(SalesChannelContext $context): void
    {
        if (!$this->settings->isEnabled($context->getSalesChannelId())) {
            throw new NotFoundHttpException();
        }
    }
}
