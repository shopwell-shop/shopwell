<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Checkout\Membership\SalesChannel\MembershipPurchaseRoute;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopwell\Storefront\Page\Account\Membership\AccountMembershipPageLoadedHook;
use Shopwell\Storefront\Page\Account\Membership\AccountMembershipPageLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The membership page: the running membership, the plans, and the orders that bought them.
 *
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class AccountMembershipController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AccountMembershipPageLoader $pageLoader,
        private readonly MembershipPurchaseRoute $membershipPurchaseRoute,
        private readonly LoyaltySettings $settings,
    ) {
    }

    #[Route(
        path: '/account/membership',
        name: 'frontend.account.membership.page',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function index(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $this->assertMembershipEnabled($context);

        $page = $this->pageLoader->load($request, $context, $customer);

        $this->hook(new AccountMembershipPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/account/membership/index.html.twig', [
            'page' => $page,
        ]);
    }

    /**
     * The snapshot the page polls while a purchase is being paid: the order that was just opened is read
     * back from the recent orders instead of adding a second endpoint.
     */
    #[Route(
        path: '/account/membership/json',
        name: 'frontend.account.membership.json',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            'XmlHttpRequest' => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function membershipJson(Request $request, SalesChannelContext $context, CustomerEntity $customer): JsonResponse
    {
        $this->assertMembershipEnabled($context);

        $page = $this->pageLoader->load($request, $context, $customer);

        return $this->json(['success' => true, 'membership' => $page->getMembership()->all()]);
    }

    /**
     * Buys a membership plan for the customer.
     *
     * The price, the tier and the duration are not taken from the request: the plan is loaded again by
     * the route, so a page that was open while the operator changed the price cannot buy at the old
     * one. What the browser gets back is only how to hand the payer over to the channel.
     */
    #[Route(
        path: '/account/membership/purchase',
        name: 'frontend.account.membership.purchase',
        options: ['seo' => false],
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            'XmlHttpRequest' => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function purchase(Request $request, SalesChannelContext $context, CustomerEntity $customer): JsonResponse
    {
        try {
            $response = $this->membershipPurchaseRoute->purchase($request, $context, $customer);
        } catch (HttpException $exception) {
            return $this->json(
                ['success' => false, 'errorCode' => $exception->getErrorCode()],
                $exception->getStatusCode()
            );
        }

        return $this->json(['success' => true, 'order' => $response->getObject()->all()]);
    }

    /**
     * The paid membership is part of the loyalty programme, so the same switch hides it: a shop that
     * does not run a programme has nothing to sell either.
     */
    private function assertMembershipEnabled(SalesChannelContext $context): void
    {
        if (!$this->settings->isEnabled($context->getSalesChannelId())) {
            throw new NotFoundHttpException();
        }
    }
}
