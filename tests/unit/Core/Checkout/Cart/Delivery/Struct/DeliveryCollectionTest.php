<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Delivery\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\CountryEntity;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DeliveryCollection::class)]
class DeliveryCollectionTest extends TestCase
{
    public function testGetShipmentsLeavesOutShippingDiscountsAndKeepsTheCollectionOrder(): void
    {
        $deliveries = new DeliveryCollection([
            $this->createDelivery('standard', 4.95),
            $this->createDelivery('discount', -4.95),
            $this->createDelivery('express', 9.99),
            $this->createDelivery('free', 0.0),
            $this->createDelivery('second-standard', 4.95),
        ]);

        static::assertSame(
            ['standard', 'express', 'free', 'second-standard'],
            array_values($deliveries->getShipments()->map(static fn (Delivery $delivery) => $delivery->getShippingMethod()->getId()))
        );
    }

    private function createDelivery(string $shippingMethodId, float $shippingCosts): Delivery
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId($shippingMethodId);

        return new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice($shippingCosts, $shippingCosts, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
    }
}
