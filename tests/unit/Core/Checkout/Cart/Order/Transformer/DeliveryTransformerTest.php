<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Order\Transformer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopwell\Core\Checkout\Cart\Order\IdStruct;
use Shopwell\Core\Checkout\Cart\Order\OrderConverter;
use Shopwell\Core\Checkout\Cart\Order\PositionStruct;
use Shopwell\Core\Checkout\Cart\Order\Transformer\DeliveryTransformer;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\System\Currency\CurrencyEntity;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DeliveryTransformer::class)]
class DeliveryTransformerTest extends TestCase
{
    public function testTransformCollection(): void
    {
        $cart = $this->createCart();
        $delivery = $cart->getDeliveries()->first();
        $lineItems = [];
        $addresses = [];
        $stateId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $result = DeliveryTransformer::transformCollection(
            $cart->getDeliveries(),
            $lineItems,
            $stateId,
            $context,
            $addresses
        );

        static::assertCount(1, $result);
        static::assertInstanceOf(Delivery::class, $delivery);
        static::assertSame(
            [...DeliveryTransformer::transform($delivery, $lineItems, $stateId, $context, $addresses), 'position' => 1],
            $result[0]
        );

        static::assertEquals(
            [
                'shippingMethodId' => $delivery->getShippingMethod()->getId(),
                'shippingCosts' => $delivery->getShippingCosts(),
                'payload' => [],
                'positions' => [],
                'stateId' => $stateId,
                'shippingDateEarliest' => $delivery->getDeliveryDate()->getEarliest()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'shippingDateLatest' => $delivery->getDeliveryDate()->getLatest()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'position' => 1,
            ],
            $result[0]
        );
    }

    public function testTransformCarriesThePayload(): void
    {
        $delivery = $this->createCart()->getDeliveries()->first();
        static::assertInstanceOf(Delivery::class, $delivery);
        $delivery->setPayload(['group' => 'warehouse-a', 'locked' => true]);

        $result = DeliveryTransformer::transform($delivery, [], Uuid::randomHex(), Context::createDefaultContext());

        static::assertSame(['group' => 'warehouse-a', 'locked' => true], $result['payload']);
    }

    public function testTransformCollectionWithDeliveryExisted(): void
    {
        $cart = $this->createCart();
        $delivery = $cart->getDeliveries()->first();
        static::assertInstanceOf(Delivery::class, $delivery);
        $delivery->addExtension(OrderConverter::ORIGINAL_ID, new IdStruct('deliveryId'));
        $lineItems = [];
        $addresses = [];
        $stateId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $result = DeliveryTransformer::transformCollection(
            $cart->getDeliveries(),
            $lineItems,
            $stateId,
            $context,
            $addresses
        );

        static::assertCount(1, $result);
        static::assertSame(
            [...DeliveryTransformer::transform($delivery, $lineItems, $stateId, $context, $addresses), 'position' => 1],
            $result[0]
        );

        static::assertEquals(
            [
                'id' => 'deliveryId',
                'shippingMethodId' => $delivery->getShippingMethod()->getId(),
                'shippingCosts' => $delivery->getShippingCosts(),
                'payload' => [],
                'positions' => [],
                'stateId' => $stateId,
                'shippingDateEarliest' => $delivery->getDeliveryDate()->getEarliest()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'shippingDateLatest' => $delivery->getDeliveryDate()->getLatest()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'position' => 1,
            ],
            $result[0]
        );
    }

    public function testTransformCollectionWithOriginalAddresses(): void
    {
        $cart = $this->createCart();
        $delivery = $cart->getDeliveries()->first();
        static::assertInstanceOf(Delivery::class, $delivery);
        $delivery->addExtension(OrderConverter::ORIGINAL_ADDRESS_ID, new IdStruct('originalAddressId'));
        $delivery->addExtension(OrderConverter::ORIGINAL_ADDRESS_VERSION_ID, new IdStruct('originalAddressVersionId'));
        $lineItems = [];
        $addresses = [];
        $stateId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $result = DeliveryTransformer::transformCollection(
            $cart->getDeliveries(),
            $lineItems,
            $stateId,
            $context,
            $addresses
        );

        static::assertCount(1, $result);
        static::assertSame(
            [...DeliveryTransformer::transform($delivery, $lineItems, $stateId, $context, $addresses), 'position' => 1],
            $result[0]
        );

        static::assertEquals(
            [
                'shippingMethodId' => $delivery->getShippingMethod()->getId(),
                'shippingCosts' => $delivery->getShippingCosts(),
                'payload' => [],
                'positions' => [],
                'stateId' => $stateId,
                'shippingOrderAddressId' => 'originalAddressId',
                'shippingOrderAddressVersionId' => 'originalAddressVersionId',
                'shippingDateEarliest' => $delivery->getDeliveryDate()->getEarliest()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'shippingDateLatest' => $delivery->getDeliveryDate()->getLatest()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'position' => 1,
            ],
            $result[0]
        );
    }

    public function testTransformCollectionNumbersTheDeliveriesInCartOrder(): void
    {
        $deliveries = new DeliveryCollection([
            $this->createDelivery(5),
            $this->createDelivery(-5),
            $this->createDelivery(10),
        ]);

        $result = DeliveryTransformer::transformCollection($deliveries, [], Uuid::randomHex(), Context::createDefaultContext());

        static::assertSame([1, 2, 3], array_column($result, 'position'));
    }

    /**
     * @param list<int|null> $storedPositions
     * @param list<int> $expectedPositions
     */
    #[DataProvider('storedPositionProvider')]
    public function testTransformCollectionRenumbersDeliveriesInTheOrderOfTheirStoredPositionsWithNewOnesLast(array $storedPositions, array $expectedPositions): void
    {
        $deliveries = new DeliveryCollection();
        foreach ($storedPositions as $storedPosition) {
            $delivery = $this->createDelivery(5);
            if ($storedPosition !== null) {
                $delivery->addExtension(OrderConverter::ORIGINAL_POSITION, new PositionStruct($storedPosition));
            }
            $deliveries->add($delivery);
        }

        $result = DeliveryTransformer::transformCollection($deliveries, [], Uuid::randomHex(), Context::createDefaultContext());

        static::assertSame($expectedPositions, array_column($result, 'position'));
    }

    public static function storedPositionProvider(): \Generator
    {
        yield 'stored positions in any order' => [[2, 1, null], [2, 1, 3]];
        yield 'gap left by a deleted delivery' => [[3, 1, null], [2, 1, 3]];
        yield 'order placed before positions were stored' => [[0, 0, null], [1, 2, 3]];
        yield 'new delivery before the stored ones' => [[null, 1, 2], [3, 1, 2]];
    }

    public function createCart(): Cart
    {
        $cart = new Cart('test');
        $cart->setPrice(
            new CartPrice(
                100,
                100,
                100,
                new CalculatedTaxCollection([
                    new CalculatedTax(0, 38, 100),
                ]),
                new TaxRuleCollection(),
                CartPrice::TAX_STATE_GROSS,
                100
            )
        );
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('1');
        $shippingMethod->setActive(true);
        $deliveryDate = new DeliveryDate(new \DateTime(), new \DateTime());
        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
        $cart->setDeliveries(new DeliveryCollection([$delivery]));
        $cart->setCustomerComment('customerCommentTest');
        $cart->setAffiliateCode('AffiliateCodeTest');
        $cart->setCampaignCode('campaignCodeTest');
        $cart->setSource('sourceTest');

        return $cart;
    }

    public function createSalesChannelMock(string $adminUserId): SalesChannelContext&Stub
    {
        $salesChannelContextMock = static::createStub(SalesChannelContext::class);
        $contextSourceMock = static::createStub(AdminSalesChannelApiSource::class);
        $sourceTest = static::createStub(AdminApiSource::class);

        $contextMockAdminSales = new Context($contextSourceMock);
        $contextMockAdminApi = new Context($sourceTest);

        $contextSourceMock->method('getOriginalContext')->willReturn($contextMockAdminApi);
        $sourceTest->method('getUserId')->willReturn($adminUserId);
        $salesChannelContextMock->method('getContext')->willReturn($contextMockAdminSales);
        $currency = new CurrencyEntity();
        $currency->setId('12345');
        $currency->setFactor(1);

        $salesChannelContextMock->method('getCurrency')->willReturn($currency);
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId('123');
        $salesChannelContextMock->method('getSalesChannel')->willReturn($salesChannelEntity);

        return $salesChannelContextMock;
    }

    private function createDelivery(float $shippingCosts): Delivery
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('1');

        return new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTime(), new \DateTime()),
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice($shippingCosts, $shippingCosts, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
    }
}
