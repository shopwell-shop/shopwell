<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\SalesChannel;

use Shopwell\Core\Checkout\Cart\AbstractCartPersister;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartCalculator;
use Shopwell\Core\Checkout\Cart\CartLocker;
use Shopwell\Core\Checkout\Cart\Event\AfterLineItemAddedEvent;
use Shopwell\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopwell\Core\Checkout\Cart\Event\CartChangedEvent;
use Shopwell\Core\Checkout\Cart\Extension\CartItemAddRouteExtension;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\RateLimiter\RateLimiter;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CartItemAddRoute extends AbstractCartItemAddRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CartCalculator $cartCalculator,
        private readonly AbstractCartPersister $cartPersister,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LineItemFactoryRegistry $lineItemFactory,
        private readonly RateLimiter $rateLimiter,
        private readonly CartLocker $cartLocker,
        private readonly ExtensionDispatcher $extensions,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getDecorated(): AbstractCartItemAddRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @param array<LineItem>|null $items
     */
    #[Route(path: '/store-api/checkout/cart/line-item', name: 'store-api.checkout.cart.add', methods: ['POST'])]
    public function add(Request $request, Cart $cart, SalesChannelContext $context, ?array $items): CartResponse
    {
        return $this->extensions->publish(
            name: CartItemAddRouteExtension::NAME,
            extension: new CartItemAddRouteExtension($request, $cart, $context, $items),
            function: $this->_add(...),
        );
    }

    /**
     * @param array<LineItem>|null $items
     */
    private function _add(Request $request, Cart $cart, SalesChannelContext $context, ?array $items): CartResponse
    {
        return $this->cartLocker->locked($context, function () use ($request, $cart, $context, $items) {
            if ($items === null) {
                $items = [];

                /** @var array<mixed> $item */
                foreach ($request->request->all('items') as $item) {
                    $items[] = $this->lineItemFactory->create($item, $context);
                }
            }

            foreach ($items as $item) {
                $this->applyRateLimits($item, $request, $context);

                $alreadyExists = $cart->has($item->getId());
                $cart->add($item);

                $this->eventDispatcher->dispatch(new BeforeLineItemAddedEvent($item, $cart, $context, $alreadyExists));
            }

            $cart->markModified();

            $cart = $this->cartCalculator->calculate($cart, $context);
            $this->cartPersister->save($cart, $context);

            $this->eventDispatcher->dispatch(new AfterLineItemAddedEvent($items, $cart, $context));
            $this->eventDispatcher->dispatch(new CartChangedEvent($cart, $context));

            $response = new CartResponse($cart);
            $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $context->getToken());

            return $response;
        });
    }

    /**
     * Promotions use the main request because CartService passes an empty request for Storefront calls.
     * Other line items keep the passed request so the existing per-item limit remains API-only.
     */
    private function applyRateLimits(LineItem $item, Request $request, SalesChannelContext $context): void
    {
        $isPromotion = $item->getType() === LineItem::PROMOTION_LINE_ITEM_TYPE;
        if ($isPromotion) {
            $request = $this->requestStack->getMainRequest() ?? $request;
        }

        $clientIp = $request->getClientIp();
        if ($clientIp === null) {
            return;
        }

        // Group IPv6 addresses in the same /64; keep IPv4 addresses unchanged.
        $clientIp = IpUtils::anonymize($clientIp, 0, 8);

        if ($isPromotion) {
            // Different promotion codes must share an allowance instead of each starting a new per-item allowance.
            $this->rateLimiter->ensureAccepted(RateLimiter::PROMOTION_CODE, $clientIp . '-' . $context->getSalesChannelId());

            return;
        }

        $cacheKey = ($item->getReferencedId() ?? $item->getId()) . '-' . $clientIp . '-' . $context->getSalesChannelId();
        $this->rateLimiter->ensureAccepted(RateLimiter::CART_ADD_LINE_ITEM, $cacheKey, $context->getSalesChannelId());
    }
}
