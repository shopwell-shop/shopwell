<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Subscriber;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Checkout\Customer\CustomerEvents;
use Shopwell\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerAddressSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'sales_channel.' . CustomerEvents::CUSTOMER_ADDRESS_LOADED_EVENT => 'salesChannelLoaded',
            'sales_channel.customer_address.partial_loaded' => 'salesChannelLoaded',
        ];
    }

    /**
     * @param SalesChannelEntityLoadedEvent<CustomerAddressEntity|PartialEntity> $event
     */
    public function salesChannelLoaded(SalesChannelEntityLoadedEvent $event): void
    {
        if (!$event->getSalesChannelContext()->getCustomer()) {
            return;
        }

        $defaultBillingAddressId = $event->getSalesChannelContext()->getCustomer()->getDefaultBillingAddressId();
        $defaultShippingAddressId = $event->getSalesChannelContext()->getCustomer()->getDefaultShippingAddressId();

        foreach ($event->getEntities() as $customerAddress) {
            $customerAddress->assign([
                'isDefaultBillingAddress' => $customerAddress->getId() === $defaultBillingAddressId,
                'isDefaultShippingAddress' => $customerAddress->getId() === $defaultShippingAddressId,
            ]);
        }
    }
}
