<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Overview;

use Shopwell\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\SalesChannel\AbstractCustomerRoute;
use Shopwell\Core\Checkout\Loyalty\SalesChannel\LoyaltyRoute;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyTierProgress;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderStates;
use Shopwell\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopwell\Core\Checkout\Wallet\SalesChannel\WalletRoute;
use Shopwell\Core\Checkout\Wallet\Service\WalletSettings;
use Shopwell\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Checkout\Order\OrderProductAvailabilityResolver;
use Shopwell\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopwell\Storefront\Page\Account\Order\AccountOrderStateCounter;
use Shopwell\Storefront\Page\GenericPageLoaderInterface;
use Shopwell\Storefront\Page\MetaInformation;
use Shopwell\Storefront\Pagelet\Newsletter\Account\NewsletterAccountPageletLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('checkout')]
class AccountOverviewPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractOrderRoute $orderRoute,
        private readonly AbstractCustomerRoute $customerRoute,
        private readonly NewsletterAccountPageletLoader $newsletterAccountPageletLoader,
        private readonly AbstractTranslator $translator,
        private readonly OrderProductAvailabilityResolver $productAvailabilityResolver,
        private readonly WalletRoute $walletRoute,
        private readonly WalletSettings $walletSettings,
        private readonly LoyaltyRoute $loyaltyRoute,
        private readonly LoyaltySettings $loyaltySettings,
        private readonly LoyaltyTierProgress $tierProgress,
        private readonly AccountOrderStateCounter $orderStateCounter
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext, CustomerEntity $customer): AccountOverviewPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountOverviewPage::createFrom($page);
        $page->setCustomer($this->loadCustomer($salesChannelContext, $customer));
        $this->setMetaInformation($page);

        // one route call answers both "what did I order last" and the single newest order the page used
        // to load separately - the newest order is simply the first of the three
        $orders = $this->loadRecentOrders($salesChannelContext, $request);
        $page->setRecentOrders($orders);

        $order = $orders->first();

        if ($order !== null) {
            $this->productAvailabilityResolver->addAvailability([$order], $salesChannelContext);

            $page->setNewestOrder($order);
        }

        // counted by the shared counter so the dashboard badge and the order-list tag cannot disagree
        $page->setPendingPaymentCount($this->orderStateCounter->countInOrderState($request, $salesChannelContext, OrderStates::STATE_OPEN));
        $page->setPendingDeliveryCount($this->orderStateCounter->countInOrderState($request, $salesChannelContext, OrderStates::STATE_IN_PROGRESS));

        $this->loadWallet($page, $request, $salesChannelContext, $customer);
        $this->loadRewards($page, $request, $salesChannelContext, $customer);

        $newslAccountPagelet = $this->newsletterAccountPageletLoader->load($request, $salesChannelContext, $customer);

        $page->setNewsletterAccountPagelet($newslAccountPagelet);

        $this->eventDispatcher->dispatch(
            new AccountOverviewPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(AccountOverviewPage $page): void
    {
        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        if ($page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('account.overviewMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );
    }

    /**
     * The last orders of the customer, newest first.
     *
     * The associations are the ones the order history needs to render a row: state, payment method,
     * currency and amount. Nothing is added here that the overview table does not print, because every
     * association of this route is a join the customer pays for.
     */
    private function loadRecentOrders(SalesChannelContext $context, Request $request): OrderCollection
    {
        $criteria = (new Criteria())
            ->addSorting(new FieldSorting('orderDateTime', FieldSorting::DESCENDING))
            ->addAssociation('primaryOrderTransaction.paymentMethod')
            ->addAssociation('primaryOrderTransaction.stateMachineState')
            ->addAssociation('primaryOrderDelivery.shippingMethod')
            ->addAssociation('primaryOrderDelivery.stateMachineState')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('lineItems')
            ->addAssociation('lineItems.cover')
            ->addAssociation('lineItems.downloads.media')
            ->addAssociation('addresses')
            ->addAssociation('currency')
            ->addAssociation('stateMachineState')
            ->addAssociation('documents.documentType')
            ->addAssociation('documents.documentMediaFile')
            ->addAssociation('documents.documentA11yMediaFile')
            ->setLimit(AccountOverviewPage::RECENT_ORDER_LIMIT)
            ->addAssociation('orderCustomer');

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria
                ->addAssociation('transactions.paymentMethod')
                ->addAssociation('transactions.stateMachineState')
                ->addAssociation('deliveries.stateMachineState');

            $criteria
                ->getAssociation('transactions')
                ->addSorting(new FieldSorting('createdAt'));
        }

        $apiRequest = $request->duplicate();

        $event = new OrderRouteRequestEvent($request, $apiRequest, $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->orderRoute
            ->load($event->getStoreApiRequest(), $context, $criteria)
            ->getOrders()
            ->getEntities();
    }

    /**
     * The balance the customer can spend right now, in the currency they are shopping in.
     *
     * The wallet route answers per currency; the dashboard shows one number, so it takes the wallet of
     * the current currency. A customer without a wallet in that currency has nothing to spend and gets
     * `0.0` rather than `null` - the feature is on, the balance is simply empty.
     */
    private function loadWallet(AccountOverviewPage $page, Request $request, SalesChannelContext $context, CustomerEntity $customer): void
    {
        if (!$this->walletSettings->isEnabled($context->getSalesChannelId())) {
            return;
        }

        $wallet = $this->walletRoute->load($request, $context, $customer)->getWallet();
        $currencyId = $context->getCurrency()->getId();

        $balance = 0.0;

        /** @var list<array<string, mixed>> $entries */
        $entries = $wallet->get('wallets') ?? [];

        foreach ($entries as $entry) {
            if (($entry['currencyId'] ?? null) === $currencyId) {
                $balance = (float) ($entry['balance'] ?? 0.0);

                break;
            }
        }

        $page->setWalletBalance($balance);
    }

    /**
     * Points, tier and the step to the next one.
     *
     * The tier ladder is resolved by {@see LoyaltyTierProgress}, the same projection the rewards page
     * uses, so both pages cannot print a different tier or a different progress bar for the same
     * customer.
     */
    private function loadRewards(AccountOverviewPage $page, Request $request, SalesChannelContext $context, CustomerEntity $customer): void
    {
        if (!$this->loyaltySettings->isEnabled($context->getSalesChannelId())) {
            return;
        }

        $rewards = $this->loyaltyRoute->load($request, $context, $customer)->getLoyalty();

        $page->setRewards($rewards);

        /** @var list<array<string, mixed>> $tiers */
        $tiers = $rewards->get('tiers') ?? [];
        $tierPoints = (int) ($rewards->get('tierPoints') ?? 0);

        [$currentTier, $nextTier] = $this->tierProgress->resolve($tiers, $rewards->get('tierId'));

        $page->setCurrentTier($currentTier);
        $page->setNextTier($nextTier);
        $page->setTierPoints($tierPoints);
        $page->setTierProgress($this->tierProgress->percentage($tierPoints, $currentTier, $nextTier));
        $page->setTierPointsToNext($nextTier === null ? null : $this->tierProgress->remainingPoints($tierPoints, $nextTier));
    }

    private function loadCustomer(SalesChannelContext $context, CustomerEntity $customer): CustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addAssociation('requestedGroup');
        $criteria->addAssociation('defaultBillingAddress.country');
        $criteria->addAssociation('defaultShippingAddress.country');

        return $this->customerRoute->load(new Request(), $context, $criteria, $customer)->getCustomer();
    }
}
