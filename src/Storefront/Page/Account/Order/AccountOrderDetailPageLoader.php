<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Order;

use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopwell\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopwell\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 *
 * @deprecated tag:v6.8.0 - Will be removed without replacement
 */
#[Package('checkout')]
class AccountOrderDetailPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractOrderRoute $orderRoute
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountOrderDetailPage
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        if (!$salesChannelContext->getCustomer()) {
            throw CartException::customerNotLoggedIn();
        }

        $orderId = $request->attributes->getString('id', $request->query->getString('id'));

        if ($orderId === '') {
            throw RoutingException::missingRequestParameter('id');
        }

        $criteria = new Criteria([$orderId]);
        $criteria
            ->addAssociation('primaryOrderTransaction.paymentMethod')
            ->addAssociation('primaryOrderTransaction.stateMachineState')
            ->addAssociation('primaryOrderDelivery.shippingMethod')
            ->addAssociation('primaryOrderDelivery.stateMachineState')
            ->addAssociation('lineItems')
            ->addAssociation('orderCustomer')
            ->addAssociation('stateMachineState')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('lineItems.cover');

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

        $event = new OrderRouteRequestEvent($request, $apiRequest, $salesChannelContext, $criteria);
        $this->eventDispatcher->dispatch($event);

        $result = $this->orderRoute
            ->load($event->getStoreApiRequest(), $salesChannelContext, $criteria);

        $order = $result->getOrders()->getEntities()->first();

        if (!$order instanceof OrderEntity) {
            throw new NotFoundHttpException();
        }

        $page = AccountOrderDetailPage::createFrom($this->genericLoader->load($request, $salesChannelContext));
        $page->setLineItems($order->getNestedLineItems());
        $page->setOrder($order);

        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        $this->eventDispatcher->dispatch(
            new AccountOrderDetailPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }
}
