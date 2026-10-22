<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\SalesChannel;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartLocker;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\Cart\LoyaltyPointCartProcessor;
use Shopwell\Core\Checkout\Loyalty\Extension\LoyaltyRedeemRouteExtension;
use Shopwell\Core\Checkout\Loyalty\LoyaltyException;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointService;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class LoyaltyRedeemRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly LoyaltyPointService $loyaltyPointService,
        private readonly LoyaltySettings $settings,
        private readonly CartService $cartService,
        private readonly CartLocker $cartLocker,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    #[Route(
        path: '/store-api/loyalty/redeem-points',
        name: 'store-api.loyalty.redeem-points',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function redeem(Request $request, Cart $cart, SalesChannelContext $context, CustomerEntity $customer): LoyaltyRedeemRouteResponse
    {
        return $this->extensions->publish(
            name: LoyaltyRedeemRouteExtension::NAME,
            extension: new LoyaltyRedeemRouteExtension($request, $cart, $context, $customer),
            function: $this->_redeem(...),
        );
    }

    private function _redeem(Request $request, Cart $cart, SalesChannelContext $context, CustomerEntity $customer): LoyaltyRedeemRouteResponse
    {
        $points = $request->request->getInt('points');

        return $this->cartLocker->locked($context, function () use ($points, $cart, $context, $customer): LoyaltyRedeemRouteResponse {
            $this->assertRedeemable($points, $customer, $context);

            $cart->addExtension(LoyaltyPointCartProcessor::EXTENSION_KEY, new ArrayStruct(['points' => $points]));
            $cart->markModified();

            return new LoyaltyRedeemRouteResponse($this->cartService->recalculate($cart, $context));
        });
    }

    private function assertRedeemable(int $points, CustomerEntity $customer, SalesChannelContext $context): void
    {
        if ($points < 0) {
            throw LoyaltyException::invalidPoints($points);
        }

        // a value of zero clears a previous redemption and is always allowed
        if ($points === 0) {
            return;
        }

        $minimum = $this->settings->minRedeemablePoints($context->getSalesChannelId());

        if ($points < $minimum) {
            throw LoyaltyException::belowMinimumPoints($points, $minimum);
        }

        $balance = $this->loyaltyPointService->getBalance($customer->getId(), $context->getContext());

        if ($points > $balance) {
            throw LoyaltyException::insufficientPoints($customer->getId(), $points, $balance);
        }
    }
}
