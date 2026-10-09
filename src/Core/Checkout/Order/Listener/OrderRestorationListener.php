<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Listener;

use Shopwell\Core\Checkout\Cart\Order\OrderRestorer;
use Shopwell\Core\Checkout\Order\OrderException;
use Shopwell\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\KernelListenerPriorities;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Package('checkout')]
class OrderRestorationListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly AbstractOrderRoute $orderRoute,
        private readonly OrderRestorer $orderRestorer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => [
                ['restoreOrderState', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_POST],
            ],
        ];
    }

    public function restoreOrderState(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->get(PlatformRequest::ATTRIBUTE_ALLOW_ORDER_RESTORATION) !== true) {
            return;
        }

        $orderId = $this->getOrderId($request);
        if ($orderId === null) {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if (!$context instanceof SalesChannelContext) {
            return;
        }

        if ($context->getCustomer() === null) {
            throw OrderException::customerNotLoggedIn();
        }

        if (!Uuid::isValid($orderId)) {
            throw OrderException::invalidUuid($orderId);
        }

        $criteria = $this->orderRestorer->addRequiredAssociations(new Criteria([$orderId]));
        $order = $this->orderRoute->load(new Request(), $context, $criteria)->getOrders()->getEntities()->get($orderId);
        if ($order === null) {
            throw OrderException::orderNotFound($orderId);
        }

        $restored = $this->orderRestorer->restore($order, $context->getContext());

        $request->attributes->set(PlatformRequest::ATTRIBUTE_EFFECTIVE_SALES_CHANNEL_CONTEXT_OBJECT, $restored->context);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_EFFECTIVE_CONTEXT_OBJECT, $restored->context->getContext());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_EFFECTIVE_CART_OBJECT, $restored->cart);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_NO_STORE, true);
        $request->attributes->remove(PlatformRequest::ATTRIBUTE_HTTP_CACHE);
        $request->attributes->set('orderId', $orderId);
    }

    private function getOrderId(Request $request): ?string
    {
        foreach ([$request->attributes, $request->query, $request->request] as $parameters) {
            if ($parameters->has('orderId')) {
                return $parameters->getString('orderId');
            }
        }

        return null;
    }
}
