<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Delivery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopwell\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Shopwell\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopwell\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DeliveryProcessor::class)]
class DeliveryProcessorTest extends TestCase
{
    public function testCollectShippingMethods(): void
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());

        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->expects($this->once())
            ->method('getShippingMethod')
            ->willReturn($shippingMethod);

        $result = $this->createMock(EntitySearchResult::class);
        $result
            ->expects($this->once())
            ->method('getEntities')->willReturn(new EntityCollection([$shippingMethod]));

        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('search')->willReturn($result);

        $processor = new DeliveryProcessor(
            static::createStub(DeliveryBuilder::class),
            static::createStub(DeliveryCalculator::class),
            $repository
        );

        $data = new CartDataCollection();
        $processor->collect($data, new Cart('test'), $context, new CartBehavior());

        static::assertInstanceOf(ShippingMethodEntity::class, $data->get($processor::buildKey($shippingMethod->getId())));
    }

    public function testProcessDeliveryCost(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $calculator = $this->createMock(DeliveryCalculator::class);
        $calculator
            ->expects($this->once())
            ->method('calculate');

        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTime(), new \DateTime()),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(0.0, 0.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
        );

        $builder = $this->createMock(DeliveryBuilder::class);
        $builder
            ->expects($this->once())
            ->method('build')
            ->willReturn(new DeliveryCollection([$delivery]));

        $processor = new DeliveryProcessor($builder, $calculator, static::createStub(EntityRepository::class));

        $manualShippingCosts = new CalculatedPrice(10.00, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection());
        $original = new Cart('test');
        $original->addExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS, $manualShippingCosts);

        $toCalculate = new Cart('calculate');
        $processor->process(new CartDataCollection(), $original, $toCalculate, $context, new CartBehavior());

        // the processor applied the manual shipping costs to the built delivery
        static::assertSame($manualShippingCosts, $delivery->getShippingCosts());
        static::assertNotCount(0, $toCalculate->getDeliveries());
    }
}
