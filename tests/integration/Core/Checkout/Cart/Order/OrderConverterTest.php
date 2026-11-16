<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Cart\Order;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\CartRuleLoader;
use Shopwell\Core\Checkout\Cart\Delivery\DeliveryPositionSplitter;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Order\IdStruct;
use Shopwell\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopwell\Core\Checkout\Cart\Order\OrderConverter;
use Shopwell\Core\Checkout\Cart\Order\OrderPersister;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopwell\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Integration\Traits\TestShortHands;

/**
 * @internal
 */
#[Package('checkout')]
class OrderConverterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TestShortHands;

    private OrderConverter $orderConverter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderConverter = static::getContainer()->get(OrderConverter::class);
    }

    public function testConvertToOrderAndSetPrimary(): void
    {
        $cartToken = Uuid::randomHex();
        $cart = $this->getCart($cartToken);
        $context = Generator::generateSalesChannelContext(customer: $this->getCustomer());

        $convertedOrder = $this->orderConverter->convertToOrder($cart, $context, new OrderConversionContext());

        static::assertSame($convertedOrder['deliveries'][0]['id'], $cartToken);
        static::assertNotNull($convertedOrder['transactions'][0]['id']);
    }

    public function testThatPrimaryDeliveryIsPositionedFirst(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $firstDeliveryId = Uuid::randomHex();
        $primaryOrderDeliveryId = Uuid::randomHex();
        $thirdDeliveryId = Uuid::randomHex();
        $fourthDeliveryId = Uuid::randomHex();

        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setIdentifier('order-line-item-identifier');
        $orderLineItem->setId('order-line-item-id');
        $orderLineItem->setQuantity(1);
        $orderLineItem->setType(LineItem::PRODUCT_LINE_ITEM_TYPE);
        $orderLineItem->setLabel('order-line-item-label');
        $orderLineItem->setGood(true);
        $orderLineItem->setRemovable(false);
        $orderLineItem->setStackable(true);

        $deliveryCollection = new OrderDeliveryCollection();
        $deliveryCollection->add($this->getOrderDelivery($firstDeliveryId, $orderLineItem));
        $deliveryCollection->add($this->getOrderDelivery($primaryOrderDeliveryId, $orderLineItem));
        $deliveryCollection->add($this->getOrderDelivery($thirdDeliveryId, $orderLineItem));
        $deliveryCollection->add($this->getOrderDelivery($fourthDeliveryId, $orderLineItem));

        $order = new OrderEntity();
        $order->setPrimaryOrderDeliveryId($primaryOrderDeliveryId);
        $order->setId(Uuid::randomHex());
        $order->setOrderNumber('10034');
        $order->setLineItems(new OrderLineItemCollection([$orderLineItem]));
        $order->setPrice(new CartPrice(19.5, 19.5, 19.5, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_FREE));
        $order->setDeliveries($deliveryCollection);

        $context = Generator::generateSalesChannelContext(customer: $this->getCustomer());

        $convertedCart = $this->orderConverter->convertToCart($order, $context->getContext());

        static::assertNotNull($convertedCart->getDeliveries()->first());
        static::assertSame($primaryOrderDeliveryId, $convertedCart->getDeliveries()->first()->getExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class)?->getId());
        static::assertNotNull($convertedCart->getDeliveries()->getAt(1));
        static::assertSame($firstDeliveryId, $convertedCart->getDeliveries()->getAt(1)->getExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class)?->getId());
        static::assertNotNull($convertedCart->getDeliveries()->getAt(2));
        static::assertSame($thirdDeliveryId, $convertedCart->getDeliveries()->getAt(2)->getExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class)?->getId());
        static::assertNotNull($convertedCart->getDeliveries()->getAt(3));
        static::assertSame($fourthDeliveryId, $convertedCart->getDeliveries()->getAt(3)->getExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class)?->getId());
    }

    public function testSplitPositionKeepsQuantityAndPricePerDeliveryThroughTheOrder(): void
    {
        $context = $this->login($this->getContext());

        $cart = new Cart(Uuid::randomHex());
        $cart->add(
            (new LineItem('split-item', LineItem::CUSTOM_LINE_ITEM_TYPE, null, 3))
                ->setLabel('Split item')
                ->setStackable(true)
                ->setPriceDefinition(new QuantityPriceDefinition(9.99, new TaxRuleCollection([new TaxRule(19)]), 3))
        );
        $cart = static::getContainer()->get(CartRuleLoader::class)->loadByCart($context, $cart, new CartBehavior())->getCart();

        $delivery = $cart->getDeliveries()->first();
        static::assertNotNull($delivery);
        $position = $delivery->getPositions()->get('split-item');
        static::assertNotNull($position);

        $shippingMethodDeliveryTime = $delivery->getShippingMethod()->getDeliveryTime();
        static::assertNotNull($shippingMethodDeliveryTime);

        [$part, $remainder] = static::getContainer()->get(DeliveryPositionSplitter::class)
            ->split($position, 2, DeliveryTime::createFromEntity($shippingMethodDeliveryTime), $context);

        $cart->setDeliveries(new DeliveryCollection([
            new Delivery(new DeliveryPositionCollection([$part]), $part->getDeliveryDate(), $delivery->getShippingMethod(), $delivery->getLocation(), $delivery->getShippingCosts()),
            new Delivery(new DeliveryPositionCollection([$remainder]), $remainder->getDeliveryDate(), $delivery->getShippingMethod(), $delivery->getLocation(), new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())),
        ]));

        $orderId = static::getContainer()->get(OrderPersister::class)->persist($cart, $context);

        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('deliveries.positions.orderLineItem');

        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        $order = $orderRepository->search($criteria, $context->getContext())->getEntities()->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getDeliveries());

        $orderPositionPrices = [];
        foreach ($order->getDeliveries() as $orderDelivery) {
            $orderPositions = $orderDelivery->getPositions();
            static::assertNotNull($orderPositions);
            static::assertCount(1, $orderPositions);

            $orderPosition = $orderPositions->first();
            static::assertNotNull($orderPosition);
            $orderPositionPrices[$orderPosition->getQuantity()] = $orderPosition->getPrice();
        }

        static::assertEquals([2 => $part->getPrice(), 1 => $remainder->getPrice()], $orderPositionPrices);

        $convertedCart = $this->orderConverter->convertToCart($order, $context->getContext());

        $cartPositionPrices = [];
        foreach ($convertedCart->getDeliveries() as $convertedDelivery) {
            static::assertCount(1, $convertedDelivery->getPositions());

            $convertedPosition = $convertedDelivery->getPositions()->get('split-item');
            static::assertNotNull($convertedPosition);
            $cartPositionPrices[$convertedPosition->getQuantity()] = $convertedPosition->getPrice();
        }

        static::assertEquals([2 => $part->getPrice(), 1 => $remainder->getPrice()], $cartPositionPrices);
    }

    private function getCustomer(): CustomerEntity
    {
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setId(Uuid::randomHex());
        $customerAddress->setName('Test');
        $customerAddress->setStreet('Test Street');
        $customerAddress->setCity('Test City');
        $customerAddress->setCountryId($this->getValidCountryId());

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setEmail('test@test.com');
        $customer->setName('Test');
        $customer->setCustomerNumber(Uuid::randomHex());
        $customer->setActiveBillingAddress($customerAddress);

        return $customer;
    }

    private function getCart(string $id): Cart
    {
        $cart = new Cart('token');

        $cart->setTransactions(
            new TransactionCollection([
                $this->getTransaction(0.5),
                $this->getTransaction(100),
            ])
        );

        $cart->setDeliveries(
            new DeliveryCollection([
                $this->getDelivery(Uuid::randomHex(), 1.0),
                $this->getDelivery($id, 10.5),
            ])
        );

        return $cart;
    }

    private function getTransaction(float $cost): Transaction
    {
        return new Transaction(
            new CalculatedPrice($cost, $cost, new CalculatedTaxCollection(), new TaxRuleCollection()),
            Uuid::randomHex()
        );
    }

    private function getDelivery(string $id, float $cost): Delivery
    {
        $billingAddress = new CustomerAddressEntity();
        $billingAddress->setId(Uuid::randomHex());
        $billingAddress->setName('foo bar');
        $billingAddress->setStreet('street');
        $billingAddress->setCity('city');
        $billingAddress->setCountryId(Uuid::randomHex());

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());

        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable('now'), new \DateTimeImmutable('now')),
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, $billingAddress),
            new CalculatedPrice($cost, $cost, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $delivery->addExtension(OrderConverter::ORIGINAL_ID, new IdStruct($id));

        return $delivery;
    }

    private function getOrderDelivery(string $id, OrderLineItemEntity $orderLineItem): OrderDeliveryEntity
    {
        $shippingAddress = new OrderAddressEntity();
        $shippingAddress->setId(Uuid::randomHex());
        $shippingAddress->setCountry(new CountryEntity());

        $orderDeliveryPosition = new OrderDeliveryPositionEntity();
        $orderDeliveryPosition->setId('order-delivery-position-id-1');
        $orderDeliveryPosition->setOrderLineItem($orderLineItem);
        $orderDeliveryPosition->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $orderDelivery = new OrderDeliveryEntity();
        $orderDelivery->setId($id);
        $orderDelivery->setOrderId('order-id');
        $orderDelivery->setOrderVersionId('order-version-id');
        $orderDelivery->setShippingMethodId('shipping-method-id');
        $orderDelivery->setShippingMethod(new ShippingMethodEntity());
        $orderDelivery->setShippingOrderAddressId('shipping-order-address-id');
        $orderDelivery->setShippingOrderAddress($shippingAddress);
        $orderDelivery->setShippingOrderAddressVersionId('shipping-order-address-version-id');
        $orderDelivery->setShippingDateEarliest(new \DateTimeImmutable('now'));
        $orderDelivery->setShippingDateLatest(new \DateTimeImmutable('now'));
        $orderDelivery->setShippingCosts(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $orderDelivery->setTrackingCodes([]);
        $orderDelivery->setPositions(new OrderDeliveryPositionCollection([$orderDeliveryPosition]));
        $orderDelivery->setStateId('state-id');

        return $orderDelivery;
    }
}
