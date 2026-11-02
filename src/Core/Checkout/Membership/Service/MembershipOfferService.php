<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\Service;

use Shopwell\Core\Checkout\Membership\SalesChannel\MembershipPresenter;
use Shopwell\Core\Checkout\Payment\Storefront\StorefrontPaymentService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * What the account page shows about buying a membership: the plans, how they can be paid, and what the
 * customer already bought.
 *
 * The three belong together on one page but come from three places, and the page renders them in one
 * call - asking for them separately would make the page slower without making it any clearer. Once the
 * membership page is a page of its own this is the read model it loads.
 *
 * @final
 */
#[Package('checkout')]
class MembershipOfferService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly MembershipPlanService $plans,
        private readonly MembershipPurchaseService $purchases,
        private readonly MembershipPresenter $presenter,
        private readonly StorefrontPaymentService $payments,
    ) {
    }

    /**
     * @return array{plans: list<array<string, mixed>>, channels: list<array<string, string>>, orders: list<array<string, mixed>>}
     */
    public function forCustomer(string $customerId, string $currencyId, string $currency, int $orderLimit, Context $context): array
    {
        return [
            'plans' => array_map($this->presenter->plan(...), $this->plans->purchasable($currencyId, $context)),
            // the account page is the same on every device, so no device is filtered out here: a plan
            // bought on a desktop is often paid by scanning it with a phone
            'channels' => $this->payments->channels($currency, null),
            'orders' => array_map(
                $this->presenter->order(...),
                $this->purchases->recentOrders($customerId, $orderLimit, $context)
            ),
        ];
    }
}
