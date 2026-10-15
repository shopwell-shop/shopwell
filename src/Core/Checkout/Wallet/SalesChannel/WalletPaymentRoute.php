<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\SalesChannel;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartLocker;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Wallet\Cart\WalletCartProcessor;
use Shopwell\Core\Checkout\Wallet\Extension\WalletPaymentRouteExtension;
use Shopwell\Core\Checkout\Wallet\Service\WalletService;
use Shopwell\Core\Checkout\Wallet\WalletException;
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
class WalletPaymentRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly WalletService $walletService,
        private readonly CartService $cartService,
        private readonly CartLocker $cartLocker,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    #[Route(
        path: '/store-api/wallet/payment',
        name: 'store-api.wallet.payment',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function pay(Request $request, Cart $cart, SalesChannelContext $context, CustomerEntity $customer): WalletPaymentRouteResponse
    {
        return $this->extensions->publish(
            name: WalletPaymentRouteExtension::NAME,
            extension: new WalletPaymentRouteExtension($request, $cart, $context, $customer),
            function: $this->_pay(...),
        );
    }

    private function _pay(Request $request, Cart $cart, SalesChannelContext $context, CustomerEntity $customer): WalletPaymentRouteResponse
    {
        $amount = (float) $request->request->get('amount', 0);

        return $this->cartLocker->locked($context, function () use ($amount, $cart, $context, $customer): WalletPaymentRouteResponse {
            $this->assertPayable($amount, $customer, $context);

            $cart->addExtension(WalletCartProcessor::EXTENSION_KEY, new ArrayStruct(['amount' => $amount]));
            $cart->markModified();

            return new WalletPaymentRouteResponse($this->cartService->recalculate($cart, $context));
        });
    }

    private function assertPayable(float $amount, CustomerEntity $customer, SalesChannelContext $context): void
    {
        if ($amount < 0.0) {
            throw WalletException::invalidAmount($amount);
        }

        // an amount of zero clears a previous wallet payment and is always allowed
        if ($amount === 0.0) {
            return;
        }

        $currencyId = $context->getCurrency()->getId();
        $balance = $this->walletService->getBalance($customer->getId(), $currencyId, $context->getContext());

        if ($amount > $balance) {
            throw WalletException::insufficientBalance($customer->getId(), $currencyId, $amount, $balance);
        }
    }
}
