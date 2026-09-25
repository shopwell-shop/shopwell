<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Gateway\SalesChannel;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Gateway\CheckoutGatewayInterface;
use Shopwell\Core\Checkout\Gateway\CheckoutGatewayResponse;
use Shopwell\Core\Checkout\Gateway\Command\Struct\CheckoutGatewayPayloadStruct;
use Shopwell\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopwell\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopwell\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopwell\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CheckoutGatewayRoute extends AbstractCheckoutGatewayRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractPaymentMethodRoute $paymentMethodRoute,
        private readonly AbstractShippingMethodRoute $shippingMethodRoute,
        private readonly CheckoutGatewayInterface $checkoutGateway,
    ) {
    }

    public function getDecorated(): AbstractCheckoutGatewayRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/checkout/gateway', name: 'store-api.checkout.gateway', methods: ['GET', 'POST'])]
    public function load(Request $request, Cart $cart, SalesChannelContext $context): CheckoutGatewayRouteResponse
    {
        $paymentCriteria = new Criteria();
        $shippingCriteria = new Criteria();

        $paymentCriteria->addAssociation('appPaymentMethod.app');
        $shippingCriteria->addAssociation('appShippingMethod.app');

        // Only load available payment and shipping methods from the routes
        $request->query->set('onlyAvailable', '1');

        $paymentMethods = $this->paymentMethodRoute->load($request, $context, $paymentCriteria)->getPaymentMethods();
        $shippingMethods = $this->shippingMethodRoute->load($request, $context, $shippingCriteria)->getShippingMethods();

        $payload = new CheckoutGatewayPayloadStruct($cart, $context, $paymentMethods, $shippingMethods);
        $response = $this->checkoutGateway->process($payload);

        $this->addBlockedMethodsCartErrors($response, $cart, $context);

        return new CheckoutGatewayRouteResponse($response->getAvailablePaymentMethods(), $response->getAvailableShippingMethods(), $response->getCartErrors());
    }

    private function addBlockedMethodsCartErrors(CheckoutGatewayResponse $response, Cart $cart, SalesChannelContext $context): void
    {
        $paymentMethod = $context->getPaymentMethod();

        if (!\in_array($paymentMethod->getId(), $response->getAvailablePaymentMethods()->getIds(), true)) {
            $response->getCartErrors()->add(
                new PaymentMethodBlockedError(
                    id: $paymentMethod->getId(),
                    name: (string) $paymentMethod->getTranslation('name'),
                    reason: 'not allowed',
                )
            );
        }

        foreach ($cart->getDeliveries() as $delivery) {
            $shippingMethod = $delivery->getShippingMethod();

            if (!\in_array($shippingMethod->getId(), $response->getAvailableShippingMethods()->getIds(), true)) {
                $response->getCartErrors()->add(
                    new ShippingMethodBlockedError(
                        id: $shippingMethod->getId(),
                        name: (string) $shippingMethod->getTranslation('name'),
                        reason: 'not allowed',
                    )
                );
            }
        }
    }
}
