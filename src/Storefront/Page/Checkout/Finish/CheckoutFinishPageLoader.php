<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Checkout\Finish;

use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Order\OrderException;
use Shopwell\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopwell\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopwell\Core\Profiling\Profiler;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('checkout')]
class CheckoutFinishPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly AbstractOrderRoute $orderRoute,
        private readonly AbstractTranslator $translator,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     * @throws OrderException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): CheckoutFinishPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = CheckoutFinishPage::createFrom($page);
        $this->setMetaInformation($page);

        Profiler::trace('finish-page-order-loading', function () use ($page, $request, $salesChannelContext): void {
            $page->setOrder($this->getOrder($request, $salesChannelContext));
        });

        $page->setChangedPayment((bool) $request->query->get('changedPayment', ''));

        $page->setPaymentFailed((bool) $request->query->get('paymentFailed', ''));

        $page->setLogoutCustomer($salesChannelContext->getCustomer()?->getGuest() && $this->systemConfigService->get('core.cart.logoutGuestAfterCheckout', $salesChannelContext->getSalesChannelId()));

        $this->eventDispatcher->dispatch(
            new CheckoutFinishPageLoadedEvent($page, $salesChannelContext, $request)
        );

        if ($page->getOrder()->getItemRounding()) {
            $salesChannelContext->setItemRounding($page->getOrder()->getItemRounding());
            $salesChannelContext->getContext()->setRounding($page->getOrder()->getItemRounding());
        }
        if ($page->getOrder()->getTotalRounding()) {
            $salesChannelContext->setTotalRounding($page->getOrder()->getTotalRounding());
        }

        return $page;
    }

    protected function setMetaInformation(CheckoutFinishPage $page): void
    {
        $page->getMetaInformation()?->setRobots('noindex,follow');
        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('checkout.finishMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     * @throws OrderException
     */
    private function getOrder(Request $request, SalesChannelContext $salesChannelContext): OrderEntity
    {
        $customer = $salesChannelContext->getCustomer();
        if ($customer === null) {
            throw CartException::customerNotLoggedIn();
        }

        $orderId = $request->query->get('orderId');
        if (!$orderId) {
            throw RoutingException::missingRequestParameter('orderId', '/orderId');
        }

        $criteria = (new Criteria([$orderId]))
            ->addFilter(new EqualsFilter('order.orderCustomer.customerId', $customer->getId()))
            ->addAssociation('primaryOrderDelivery.shippingMethod')
            ->addAssociation('primaryOrderDelivery.shippingOrderAddress.salutation')
            ->addAssociation('primaryOrderDelivery.shippingOrderAddress.country')
            ->addAssociation('primaryOrderDelivery.shippingOrderAddress.countryState')
            ->addAssociation('primaryOrderTransaction.paymentMethod')
            ->addAssociation('lineItems.cover')
            ->addAssociation('billingAddress.salutation')
            ->addAssociation('billingAddress.country')
            ->addAssociation('billingAddress.countryState')
            ->addAssociation('currency');

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria
                ->addAssociation('transactions.paymentMethod')
                ->addAssociation('deliveries.shippingMethod')
                ->addAssociation('deliveries.shippingOrderAddress.salutation')
                ->addAssociation('deliveries.shippingOrderAddress.country')
                ->addAssociation('deliveries.shippingOrderAddress.countryState');

            $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));
        }

        $this->eventDispatcher->dispatch(
            new CheckoutFinishPageOrderCriteriaEvent($criteria, $salesChannelContext)
        );

        try {
            $searchResult = $this->orderRoute
                ->load($request->duplicate(), $salesChannelContext, $criteria)
                ->getOrders();
        } catch (InvalidUuidException) {
            throw OrderException::orderNotFound($orderId);
        }

        /** @var OrderEntity|null $order */
        $order = $searchResult->getEntities()->get($orderId);

        if (!$order) {
            throw OrderException::orderNotFound($orderId);
        }

        return $order;
    }
}
