<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\SalesChannel;

use Shopwell\Core\Checkout\Cart\AbstractCartPersister;
use Shopwell\Core\Checkout\Cart\CartLocker;
use Shopwell\Core\Checkout\Cart\Event\CartDeletedEvent;
use Shopwell\Core\Checkout\Cart\Extension\CartDeleteRouteExtension;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\NoContentResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CartDeleteRoute extends AbstractCartDeleteRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCartPersister $cartPersister,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CartLocker $cartLocker,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractCartDeleteRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/checkout/cart', name: 'store-api.checkout.cart.delete', methods: ['DELETE'])]
    public function delete(SalesChannelContext $context): NoContentResponse
    {
        return $this->extensions->publish(
            name: CartDeleteRouteExtension::NAME,
            extension: new CartDeleteRouteExtension($context),
            function: $this->_delete(...),
        );
    }

    private function _delete(SalesChannelContext $context): NoContentResponse
    {
        return $this->cartLocker->locked($context, function () use ($context) {
            $this->cartPersister->delete($context->getToken(), $context);

            $cartDeleteEvent = new CartDeletedEvent($context);
            $this->eventDispatcher->dispatch($cartDeleteEvent);

            return new NoContentResponse();
        });
    }
}
