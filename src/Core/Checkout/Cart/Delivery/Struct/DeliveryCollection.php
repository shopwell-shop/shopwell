<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Delivery\Struct;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Order\IdStruct;
use Shopwell\Core\Checkout\Cart\Order\OrderConverter;
use Shopwell\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * @extends Collection<Delivery>
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Checkout\Cart\CartSerializationCleanerTest
 */
#[Package('checkout')]
class DeliveryCollection extends Collection
{
    /**
     * Sorts the delivery collection by earliest delivery date
     */
    public function sortDeliveries(): self
    {
        $this->sort(static function (Delivery $a, Delivery $b) {
            if ($a->getLocation() !== $b->getLocation()) {
                return -1;
            }

            return (int) ($a->getDeliveryDate()->getEarliest() > $b->getDeliveryDate()->getEarliest());
        });

        return $this;
    }

    public function getDelivery(DeliveryDate $deliveryDate, ShippingLocation $location): ?Delivery
    {
        foreach ($this->getIterator() as $delivery) {
            if ($delivery->getDeliveryDate()->getEarliest()->format('Y-m-d') !== $deliveryDate->getEarliest()->format('Y-m-d')) {
                continue;
            }

            if ($delivery->getDeliveryDate()->getLatest()->format('Y-m-d') !== $deliveryDate->getLatest()->format('Y-m-d')) {
                continue;
            }

            if ($delivery->getLocation() !== $location) {
                continue;
            }

            return $delivery;
        }

        return null;
    }

    public function contains(LineItem $item): bool
    {
        foreach ($this->getIterator() as $delivery) {
            if ($delivery->getPositions()->has($item->getId())) {
                return true;
            }
        }

        return false;
    }

    public function getShippingCosts(): PriceCollection
    {
        return new PriceCollection(
            $this->map(static fn (Delivery $delivery) => $delivery->getShippingCosts())
        );
    }

    public function getAddresses(): CustomerAddressCollection
    {
        $addresses = new CustomerAddressCollection();
        foreach ($this->getIterator() as $delivery) {
            $address = $delivery->getLocation()->getAddress();
            if ($address !== null) {
                $addresses->add($address);
            }
        }

        return $addresses;
    }

    /**
     * Returns the primary delivery, or the first with shipping costs >= 0 as fallback
     */
    public function getPrimaryDelivery(?string $primaryDeliveryId): ?Delivery
    {
        if ($primaryDeliveryId) {
            $delivery = $this->firstWhere(static function (Delivery $delivery) use ($primaryDeliveryId) {
                return $delivery->getExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class)?->getId() === $primaryDeliveryId;
            });
        }

        return $delivery ?? $this->filter(static fn (Delivery $delivery) => $delivery->getShippingCosts()->getTotalPrice() >= 0)->first();
    }

    public function getApiAlias(): string
    {
        return 'cart_delivery_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return Delivery::class;
    }
}
