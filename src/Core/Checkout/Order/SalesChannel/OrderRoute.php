<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\SalesChannel;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\Rule\PaymentMethodRule;
use Shopwell\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopwell\Core\Checkout\Customer\Service\GuestAuthenticator;
use Shopwell\Core\Checkout\Order\Event\OrderCriteriaEvent;
use Shopwell\Core\Checkout\Order\Extension\OrderRouteExtension;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Order\OrderException;
use Shopwell\Core\Checkout\Promotion\PromotionCollection;
use Shopwell\Core\Checkout\Promotion\PromotionEntity;
use Shopwell\Core\Content\Rule\RuleEntity;
use Shopwell\Core\Framework\Adapter\Database\ReplicaConnection;
use Shopwell\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopwell\Core\Framework\RateLimiter\RateLimiter;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Rule\Container\Container;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class OrderRoute extends AbstractOrderRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     * @param EntityRepository<PromotionCollection> $promotionRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $promotionRepository,
        private readonly RateLimiter $rateLimiter,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AccountService $accountService,
        private readonly GuestAuthenticator $guestAuthenticator,
        private readonly ClockInterface $clock,
        private readonly ExtensionDispatcher $extensions,
        private readonly int $deepLinkExpireDays = 30,
    ) {
    }

    public function getDecorated(): AbstractOrderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/order',
        name: 'store-api.order',
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => OrderDefinition::ENTITY_NAME],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): OrderRouteResponse
    {
        return $this->extensions->publish(
            name: OrderRouteExtension::NAME,
            extension: new OrderRouteExtension($request, $context, $criteria),
            function: $this->_load(...),
        );
    }

    private function _load(Request $request, SalesChannelContext $context, Criteria $criteria): OrderRouteResponse
    {
        ReplicaConnection::ensurePrimary();

        $criteria->addFilter(new EqualsFilter('order.salesChannelId', $context->getSalesChannelId()));

        $criteria->getAssociation('documents')
            ->addFilter(new EqualsFilter('config.displayInCustomerAccount', 'true'))
            ->addFilter(new EqualsFilter('sent', true));

        $criteria->addAssociations(['billingAddress', 'orderCustomer.customer', 'primaryOrderDelivery']);

        if (!Feature::isActive('v6.8.0.0')) {
            $criteria->addAssociation('deliveries');
        }

        $deepLinkFilter = \current(array_filter(
            $criteria->getFilters(),
            static fn (Filter $filter) => $filter instanceof EqualsFilter && ($filter->getField() === 'order.deepLinkCode' || $filter->getField() === 'deepLinkCode')
        )) ?: null;

        \assert($deepLinkFilter === null || $deepLinkFilter instanceof EqualsFilter);

        if ($context->getCustomer()) {
            $criteria->addFilter(new EqualsFilter('order.orderCustomer.customerId', $context->getCustomerId()));
        } elseif ($deepLinkFilter === null) {
            // @deprecated tag:v6.8.0 - remove this if block
            if (!Feature::isActive('v6.8.0.0')) {
                throw CartException::customerNotLoggedIn(); // @phpstan-ignore shopwell.domainException
            }
            throw OrderException::customerNotLoggedIn();
        }

        $this->eventDispatcher->dispatch(new OrderCriteriaEvent($criteria, $context));

        $orderResult = $this->orderRepository->search($criteria, $context->getContext());
        $orders = $orderResult->getEntities();

        // remove old orders only if there is a deeplink filter
        if ($deepLinkFilter !== null) {
            $orders = $this->filterOldOrders($orders);
        }

        // Handle guest authentication if deeplink is set
        if ($deepLinkFilter !== null && !$context->getCustomer()) {
            try {
                $cacheKey = strtolower((string) $deepLinkFilter->getValue()) . '-' . $request->getClientIp();

                $this->rateLimiter->ensureAccepted(RateLimiter::GUEST_LOGIN, $cacheKey);
            } catch (RateLimitExceededException $exception) {
                throw OrderException::customerAuthThrottledException($exception->getWaitTime(), $exception);
            }

            $order = $orders->first();

            if ($order === null) {
                throw OrderException::guestNotAuthenticated();
            }

            if (Feature::isActive('v6.8.0.0')) {
                // feature flag due to different exceptions
                $this->guestAuthenticator->validate($order, $request);
            } else {
                $this->checkGuestAuth($order, $request);
            }

            if (RequestParamHelper::get($request, 'login') && $customerId = $order->getOrderCustomer()?->getCustomerId()) {
                $newContextToken = $this->accountService->loginById($customerId, $context);
            }
        }

        if (isset($cacheKey)) {
            $this->rateLimiter->reset(RateLimiter::GUEST_LOGIN, $cacheKey);
        }

        $response = new OrderRouteResponse($orderResult);
        if ($request->query->getBoolean('checkPromotion') === true) {
            foreach ($orders as $order) {
                $promotions = $this->getActivePromotions($order, $context);
                $changeable = true;
                foreach ($promotions as $promotion) {
                    $changeable = $this->checkPromotion($promotion);
                    if ($changeable === true) {
                        break;
                    }
                }
                $response->addPaymentChangeable([$order->getId() => $changeable]);
            }
        }

        if (isset($newContextToken)) {
            $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $newContextToken);
        }

        return $response;
    }

    private function getActivePromotions(OrderEntity $order, SalesChannelContext $context): PromotionCollection
    {
        $promotionIds = [];
        foreach ($order->getLineItems() ?? [] as $lineItem) {
            $payload = $lineItem->getPayload();
            if (isset($payload['promotionId']) && \is_string($payload['promotionId'])) {
                $promotionIds[] = $payload['promotionId'];
            }
        }

        if (!$promotionIds) {
            return new PromotionCollection();
        }

        $criteria = (new Criteria($promotionIds))
            ->addAssociation('cartRules');

        return $this->promotionRepository->search($criteria, $context->getContext())->getEntities();
    }

    private function checkRuleType(Container $rule): bool
    {
        foreach ($rule->getRules() as $nestedRule) {
            if ($nestedRule instanceof Container && $this->checkRuleType($nestedRule) === false) {
                return false;
            }
            if ($nestedRule instanceof PaymentMethodRule) {
                return false;
            }
        }

        return true;
    }

    private function checkPromotion(PromotionEntity $promotion): bool
    {
        if ($promotion->getCartRules() === null) {
            return true;
        }

        foreach ($promotion->getCartRules() as $cartRule) {
            if (!$this->checkCartRule($cartRule)) {
                return false;
            }
        }

        return true;
    }

    private function checkCartRule(RuleEntity $cartRule): bool
    {
        $payload = $cartRule->getPayload();
        if (!$payload instanceof Container) {
            return true;
        }

        foreach ($payload->getRules() as $rule) {
            if ($rule instanceof Container && $this->checkRuleType($rule) === false) {
                return false;
            }
        }

        return true;
    }

    private function filterOldOrders(OrderCollection $orders): OrderCollection
    {
        // Search with deepLinkCode needs updatedAt Filter
        $latestOrderDate = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->modify(-abs($this->deepLinkExpireDays) . ' Day');

        return $orders->filter(static fn (OrderEntity $order) => $order->getCreatedAt() > $latestOrderDate || $order->getUpdatedAt() > $latestOrderDate);
    }

    /**
     * @deprecated tag:v6.8.0 - was replaced by GuestAuthenticator::validateGuestAuthentication
     */
    private function checkGuestAuth(?OrderEntity $order, Request $request): void
    {
        if ($order === null) {
            throw OrderException::guestNotAuthenticated();
        }

        $orderCustomer = $order->getOrderCustomer();
        if ($orderCustomer === null) {
            throw CartException::customerNotLoggedIn(); // @phpstan-ignore shopwell.domainException
        }

        $guest = $orderCustomer->getCustomer() !== null && $orderCustomer->getCustomer()->getGuest();
        // Throw exception when customer is not guest
        if (!$guest) {
            throw CartException::customerNotLoggedIn(); // @phpstan-ignore shopwell.domainException
        }

        // Verify email and zip code with this order
        $email = RequestParamHelper::get($request, 'email', false);
        $zipcode = RequestParamHelper::get($request, 'zipcode', false);

        if ($email && $zipcode) {
            $billingZipCode = $order->getBillingAddress()?->getZipcode();
            if ($billingZipCode === null
                || strtolower($email) !== strtolower($orderCustomer->getEmail())
                || strtoupper($zipcode) !== strtoupper($billingZipCode)) {
                throw OrderException::wrongGuestCredentials();
            }
        } else {
            throw OrderException::guestNotAuthenticated();
        }
    }
}
