<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Subscriber;

use Shopwell\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopwell\Core\Checkout\Cart\Event\BeforeLineItemRemovedEvent;
use Shopwell\Core\Checkout\Cart\Event\CartDeletedEvent;
use Shopwell\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopwell\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
readonly class CartOrderEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AbstractContextSwitchRoute $contextSwitchRoute,
        private LineItemGroupBuilder $lineItemGroupBuilder
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CartDeletedEvent::class => ['handleContextAddress', 1],
            CheckoutOrderPlacedEvent::class => ['handleContextAddress', 1],
            BeforeLineItemAddedEvent::class => 'resetBuilder',
            BeforeLineItemRemovedEvent::class => 'resetBuilder',
        ];
    }

    public function handleContextAddress(CartDeletedEvent|CheckoutOrderPlacedEvent $event): void
    {
        $this->contextSwitchRoute->switchContext(new RequestDataBag([
            SalesChannelContextService::SHIPPING_ADDRESS_ID => null,
            SalesChannelContextService::BILLING_ADDRESS_ID => null,
        ]), $event->getSalesChannelContext());
    }

    public function resetBuilder(BeforeLineItemAddedEvent|BeforeLineItemRemovedEvent $event): void
    {
        // We must reset the calculated results when an line item is added or removed.
        $this->lineItemGroupBuilder->reset();
    }
}
