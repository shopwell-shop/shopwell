<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\SalesChannel;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\CartRuleLoader;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingCost;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingCostCollection;
use Shopwell\Core\Checkout\CheckoutPermissions;
use Shopwell\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\Profiling\Profiler;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ShippingCostRoute extends AbstractShippingCostRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<ShippingMethodCollection> $shippingMethodRepository
     */
    public function __construct(
        private readonly EntityRepository $shippingMethodRepository,
        private readonly CartRuleLoader $cartRuleLoader,
        private readonly AbstractCheckoutGatewayRoute $checkoutGatewayRoute
    ) {
    }

    public function getDecorated(): AbstractShippingCostRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * Calculates shipping costs for the current cart and the requested shipping methods.
     *
     * This route can be expensive because alternative shipping methods require separate cart recalculations.
     * Only call it when shipping costs are actually needed and prefer adding a cache layer for repeated requests.
     *
     * @param non-empty-list<string>|null $availableShippingMethodIds
     */
    #[Route(
        path: '/store-api/shipping-cost/cart',
        name: 'store-api.shipping-cost.cart',
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function shippingCostsCart(Cart $cart, SalesChannelContext $salesChannelContext, ?array $availableShippingMethodIds = null): ShippingCostRouteResponse
    {
        return Profiler::trace('shipping-cost-calculator::cart', function () use ($cart, $salesChannelContext, $availableShippingMethodIds) {
            $shippingCosts = new ShippingCostCollection();

            if ($availableShippingMethodIds === null) {
                $request = new Request();
                $request->request->set('onlyAvailable', true);

                $availableShippingMethodIds = $this->checkoutGatewayRoute
                    ->load($request, $cart, $salesChannelContext)
                    ->getShippingMethods()
                    ->getKeys();

                if ($availableShippingMethodIds === []) {
                    return new ShippingCostRouteResponse($shippingCosts);
                }
            }

            $shippingMethods = $this->loadShippingMethods($salesChannelContext, $availableShippingMethodIds);
            foreach ($shippingMethods as $shippingMethod) {
                if ($shippingMethod->getId() === $salesChannelContext->getShippingMethod()->getId()) {
                    $deliveries = $cart->getDeliveries();
                } else {
                    $deliveries = $this->resolveCartDeliveries(
                        $shippingMethod,
                        $salesChannelContext,
                        $cart,
                    );
                }

                $delivery = $deliveries->getPrimaryDelivery(null);
                if ($delivery !== null) {
                    $shippingCosts->set($shippingMethod->getId(), new ShippingCost(
                        $deliveries->getShippingCosts()->sum(),
                        $delivery->getDeliveryDate(),
                        $shippingMethod,
                    ));
                }
            }

            return new ShippingCostRouteResponse($shippingCosts);
        });
    }

    /**
     * @param non-empty-list<string> $shippingMethodIds
     */
    private function loadShippingMethods(SalesChannelContext $context, array $shippingMethodIds): ShippingMethodCollection
    {
        $criteria = (new Criteria($shippingMethodIds))
            ->addAssociations(['deliveryTime', 'tax'])
            ->setTitle('cart::shipping-methods');

        $criteria->getAssociation('prices')
            ->addFilter(new EqualsAnyFilter('ruleId', [null, ...$context->getRuleIds()]));

        return $this->shippingMethodRepository->search($criteria, $context->getContext())->getEntities();
    }

    private function resolveCartDeliveries(
        ShippingMethodEntity $shippingMethod,
        SalesChannelContext $salesChannelContext,
        Cart $originalCart,
    ): DeliveryCollection {
        $clonedContext = clone $salesChannelContext;
        $cart = clone $originalCart;
        // the what-if calculation must never address the customer's persisted cart
        $cart->setToken(Uuid::randomHex());

        // Setting data to avoid loading them twice - and separate
        $cart->getData()->set('shipping-method-' . $shippingMethod->getId(), $shippingMethod);
        $clonedContext->assign(['shippingMethod' => $shippingMethod]);

        $behavior = [
            ...$salesChannelContext->getPermissions(),
            CheckoutPermissions::SKIP_CART_PERSISTENCE => true,
        ];

        return $this->cartRuleLoader->loadByCart($clonedContext, $cart, new CartBehavior($behavior), true)
            ->getCart()
            ->getDeliveries();
    }
}
