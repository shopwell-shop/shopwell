<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\SalesChannel;

use Shopwell\Core\Checkout\Cart\AbstractCartPersister;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartCalculator;
use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\CartLocker;
use Shopwell\Core\Checkout\Cart\Event\AfterLineItemRemovedEvent;
use Shopwell\Core\Checkout\Cart\Event\BeforeLineItemRemovedEvent;
use Shopwell\Core\Checkout\Cart\Event\CartChangedEvent;
use Shopwell\Core\Checkout\Cart\Extension\CartItemRemoveRouteExtension;
use Shopwell\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CartItemRemoveRoute extends AbstractCartItemRemoveRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CartCalculator $cartCalculator,
        private readonly AbstractCartPersister $cartPersister,
        private readonly CartLocker $cartLocker,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractCartItemRemoveRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/checkout/cart/line-item', name: 'store-api.checkout.cart.remove-item', methods: ['DELETE'])]
    #[Route(path: '/store-api/checkout/cart/line-item/delete', name: 'store-api.checkout.cart.remove-item-v2', methods: ['POST'])]
    public function remove(Request $request, Cart $cart, SalesChannelContext $context): CartResponse
    {
        return $this->extensions->publish(
            name: CartItemRemoveRouteExtension::NAME,
            extension: new CartItemRemoveRouteExtension($request, $cart, $context),
            function: $this->_remove(...),
        );
    }

    private function _remove(Request $request, Cart $cart, SalesChannelContext $context): CartResponse
    {
        return $this->cartLocker->locked($context, function () use ($request, $cart, $context) {
            $ids = RequestParamHelper::get($request, 'ids');
            $lineItems = [];

            foreach ($ids as $id) {
                if (!\is_string($id)) {
                    throw CartException::lineItemNotFound((string) $id);
                }

                $lineItem = $cart->get($id);

                if (!$lineItem) {
                    throw CartException::lineItemNotFound($id);
                }
                $lineItems[] = $lineItem;

                $cart->remove($id);

                $this->eventDispatcher->dispatch(new BeforeLineItemRemovedEvent($lineItem, $cart, $context));

                $cart->markModified();
            }

            $cart = $this->cartCalculator->calculate($cart, $context);
            $this->cartPersister->save($cart, $context);

            $this->eventDispatcher->dispatch(new AfterLineItemRemovedEvent($lineItems, $cart, $context));
            $this->eventDispatcher->dispatch(new CartChangedEvent($cart, $context));

            return new CartResponse($cart);
        });
    }
}
