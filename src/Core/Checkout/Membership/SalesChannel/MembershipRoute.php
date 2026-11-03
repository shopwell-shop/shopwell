<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\SalesChannel;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Membership\Extension\MembershipRouteExtension;
use Shopwell\Core\Checkout\Membership\Service\MembershipOfferService;
use Shopwell\Core\Checkout\Membership\Service\MembershipSubscriptionService;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The membership page: what can be bought, what it costs, and what the customer already holds.
 *
 * This is a separate read from the loyalty route on purpose. Points and tiers are how much a customer
 * *earned*, a paid membership is what they *bought* - a shop that switches the paid membership off still
 * shows points, and a customer who never buys one still earns tiers. Serving both from one payload meant
 * every page loaded data it does not render, and every change to one domain had to be re-checked against
 * the other page.
 *
 * What the page shows about the running membership comes from the subscription, not from the
 * `tier_id` / `membership_paid_until` projection on the loyalty account: the projection sits
 * there so the rest of the platform can read "which tier applies" without knowing that memberships
 * exist, and a projection is not a place to read the period from.
 */
#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class MembershipRoute
{
    private const RECENT_ORDER_LIMIT = 5;

    /**
     * @internal
     */
    public function __construct(
        private readonly MembershipOfferService $offer,
        private readonly MembershipSubscriptionService $subscriptions,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    #[Route(
        path: '/store-api/membership',
        name: 'store-api.membership',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_GET]
    )]
    public function load(Request $request, SalesChannelContext $context, CustomerEntity $customer): MembershipRouteResponse
    {
        return $this->extensions->publish(
            name: MembershipRouteExtension::NAME,
            extension: new MembershipRouteExtension($context, $customer),
            function: $this->_load(...),
        );
    }

    private function _load(SalesChannelContext $context, CustomerEntity $customer): MembershipRouteResponse
    {
        $offer = $this->offer->forCustomer(
            $customer->getId(),
            $context->getCurrency()->getId(),
            $context->getCurrency()->getIsoCode(),
            self::RECENT_ORDER_LIMIT,
            $context->getContext()
        );

        $subscription = $this->subscriptions->current($customer->getId(), $context->getContext());

        return new MembershipRouteResponse(new ArrayStruct([
            'plans' => $offer['plans'],
            'channels' => $offer['channels'],
            'orders' => $offer['orders'],
            'subscription' => $subscription === null ? null : [
                'id' => $subscription->getId(),
                // the name is the one stored with the subscription: a plan that was renamed afterwards
                // must not rewrite what the customer agreed to
                'planName' => $subscription->getPlanName(),
                'tierId' => $subscription->getLoyaltyTierId(),
                'state' => $subscription->getState(),
                'startsAt' => $subscription->getStartsAt()->format(\DATE_ATOM),
                'endsAt' => $subscription->getEndsAt()->format(\DATE_ATOM),
                'amount' => $subscription->getAmount(),
                'currency' => $subscription->getCurrency(),
            ],
        ]));
    }
}
