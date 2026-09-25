<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Subscriber;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Checkout\Customer\CustomerEvents;
use Shopwell\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopwell\Core\Checkout\Order\OrderEvents;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class AddressHashSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CustomerEvents::CUSTOMER_ADDRESS_LOADED_EVENT => 'generateAddressHash',
            OrderEvents::ORDER_ADDRESS_LOADED_EVENT => 'generateAddressHash',
        ];
    }

    /**
     * @param EntityLoadedEvent<CustomerAddressEntity|OrderAddressEntity> $event
     */
    public function generateAddressHash(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $address) {
            $address->setHash(Hasher::hash([
                'firstName' => $address->getFirstName(),
                'lastName' => $address->getLastName(),
                'zipcode' => $address->getZipcode(),
                'city' => $address->getCity(),
                'company' => $address->getCompany(),
                'department' => $address->getDepartment(),
                'title' => $address->getTitle(),
                'street' => $address->getStreet(),
                'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
                'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
                'countryId' => $address->getCountryId(),
                'countryStateId' => $address->getCountryStateId(),
            ], 'sha256'));
        }
    }
}
