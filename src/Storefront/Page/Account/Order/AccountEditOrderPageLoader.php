<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Account\Order;

use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopwell\Core\Checkout\Cart\Order\OrderConverter;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Order\OrderException;
use Shopwell\Core\Checkout\Order\OrderStates;
use Shopwell\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopwell\Core\Checkout\Order\SalesChannel\OrderRouteResponse;
use Shopwell\Core\Checkout\Order\SalesChannel\OrderService;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopwell\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
use Shopwell\Storefront\Event\RouteRequest\PaymentMethodRouteRequestEvent;
use Shopwell\Storefront\Page\GenericPageLoaderInterface;
use Shopwell\Storefront\Page\MetaInformation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('checkout')]
class AccountEditOrderPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractOrderRoute $orderRoute,
        private readonly AbstractCheckoutGatewayRoute $checkoutGatewayRoute,
        private readonly OrderConverter $orderConverter,
        private readonly OrderService $orderService,
        private readonly AbstractTranslator $translator,
        private readonly CartService $cartService
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     * @throws OrderException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): AccountEditOrderPage
    {
        if (!$salesChannelContext->getCustomer() && !$request->query->getBoolean('deepLinkCode')) {
            throw CartException::customerNotLoggedIn();
        }

        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AccountEditOrderPage::createFrom($page);
        $this->setMetaInformation($page);

        $orderRouteResponse = $this->getOrder($request, $salesChannelContext);

        /** @var OrderEntity $order */
        $order = $orderRouteResponse->getOrders()->getEntities()->first();

        if ($this->isOrderCancelled($order)) {
            throw OrderException::orderCancelled($order->getId());
        }

        if ($this->isOrderPaid($order)) {
            throw OrderException::orderAlreadyPaid($order->getId());
        }

        $paymentMethods = $this->getPaymentMethods($salesChannelContext, $request, $order);

        $page->setOrder($order);
        $page->setPaymentChangeable($this->isPaymentChangeable($orderRouteResponse, $page));
        $page->setPaymentMethods($paymentMethods);
        $page->setSelectedPaymentMethodId($this->getSelectedPaymentMethodId($request, $order, $paymentMethods));
        $page->setDeepLinkCode($request->query->get('deepLinkCode'));

        $this->eventDispatcher->dispatch(
            new AccountEditOrderPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(AccountEditOrderPage $page): void
    {
        if ($page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        $page->getMetaInformation()?->setRobots('noindex,follow');
        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('account.completePaymentMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );
    }

    private function getOrder(Request $request, SalesChannelContext $context): OrderRouteResponse
    {
        $criteria = $this->createCriteria($request, $context);
        $apiRequest = $request->duplicate();
        $apiRequest->query->set('checkPromotion', true);

        $event = new OrderRouteRequestEvent($request, $apiRequest, $context, $criteria);
        $this->eventDispatcher->dispatch($event);

        return $this->orderRoute
            ->load($event->getStoreApiRequest(), $context, $criteria);
    }

    private function createCriteria(Request $request, SalesChannelContext $context): Criteria
    {
        $orderId = $request->attributes->getString('orderId');
        if ($orderId) {
            $criteria = new Criteria([$orderId]);
        } else {
            if (Feature::isActive('v6.8.0.0')) {
                throw OrderException::invalidUuid($orderId);
            }
            $criteria = new Criteria();
        }

        $criteria
            ->addAssociation('primaryOrderDelivery.shippingOrderAddress.salutation')
            ->addAssociation('primaryOrderDelivery.shippingOrderAddress.country')
            ->addAssociation('primaryOrderDelivery.shippingOrderAddress.countryState')
            ->addAssociation('primaryOrderDelivery.stateMachineState')
            ->addAssociation('primaryOrderTransaction.stateMachineState')
            ->addAssociation('lineItems.cover')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('billingAddress.salutation')
            ->addAssociation('billingAddress.country')
            ->addAssociation('stateMachineState')
            ->addAssociation('billingAddress.countryState');

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria
                ->addAssociation('deliveries.shippingOrderAddress.salutation')
                ->addAssociation('deliveries.shippingOrderAddress.country')
                ->addAssociation('deliveries.shippingOrderAddress.countryState')
                ->addAssociation('deliveries.stateMachineState')
                ->addAssociation('transactions.stateMachineState');
        }

        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));

        if ($context->getCustomer()) {
            $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $context->getCustomerId()));
        } elseif ($request->query->get('deepLinkCode')) {
            $criteria->addFilter(new EqualsFilter('deepLinkCode', $request->query->get('deepLinkCode')));
        } else {
            throw CartException::customerNotLoggedIn();
        }

        return $criteria;
    }

    private function getPaymentMethods(SalesChannelContext $context, Request $request, OrderEntity $order): PaymentMethodCollection
    {
        $routeRequest = $request->duplicate();
        $routeRequest->query->set('onlyAvailable', '1');

        $event = new PaymentMethodRouteRequestEvent($request, $routeRequest, $context);
        $this->eventDispatcher->dispatch($event);

        $cart = $this->orderConverter->convertToCart($order, $context->getContext());
        $orderContext = $this->orderConverter->assembleSalesChannelContext($order, $context->getContext());

        $cart->setToken($orderContext->getToken());
        $this->cartService->setCart($cart);

        $options = $this->checkoutGatewayRoute->load($event->getStoreApiRequest(), $cart, $orderContext);

        $paymentMethods = $options->getPaymentMethods()->filterByProperty('afterOrderEnabled', true);
        $paymentMethods->sortPaymentMethodsByPreference($context);

        return $paymentMethods;
    }

    private function getSelectedPaymentMethodId(Request $request, OrderEntity $order, PaymentMethodCollection $paymentMethods): ?string
    {
        $requestedPaymentMethodId = $request->query->get('paymentMethodId');

        if (\is_string($requestedPaymentMethodId) && $paymentMethods->has($requestedPaymentMethodId)) {
            return $requestedPaymentMethodId;
        }

        $transaction = $order->getPrimaryOrderTransaction();

        if (!Feature::isActive('v6.8.0.0')) {
            $transaction = $order->getTransactions()?->last();
        }

        return $transaction?->getPaymentMethodId();
    }

    private function isOrderCancelled(OrderEntity $order): bool
    {
        $stateMachineState = $order->getStateMachineState();

        if ($stateMachineState === null) {
            return false;
        }

        return $stateMachineState->getTechnicalName() === OrderStates::STATE_CANCELLED;
    }

    private function isOrderPaid(OrderEntity $order): bool
    {
        $transaction = $order->getPrimaryOrderTransaction();

        if (!Feature::isActive('v6.8.0.0')) {
            $transactions = $order->getTransactions();

            if ($transactions === null) {
                return false;
            }

            $transaction = $transactions->last();
        }

        if ($transaction === null) {
            return false;
        }

        $stateMachineState = $transaction->getStateMachineState();
        if ($stateMachineState === null) {
            return false;
        }

        return $stateMachineState->getTechnicalName() === OrderTransactionStates::STATE_PAID;
    }

    private function isPaymentChangeable(OrderRouteResponse $orderRouteResponse, AccountEditOrderPage $page): bool
    {
        $isChangeableByResponse = $orderRouteResponse->getPaymentsChangeable()[$page->getOrder()->getId()] ?? true;
        $isChangeableByTransactionState = $this->orderService->isPaymentChangeableByTransactionState($page->getOrder());

        return $isChangeableByResponse && $isChangeableByTransactionState;
    }
}
