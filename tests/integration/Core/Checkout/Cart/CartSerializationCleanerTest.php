<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartSerializationCleaner;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopwell\Core\Checkout\Cart\Event\CartBeforeSerializationEvent;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopwell\Core\System\Country\CountryEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
class CartSerializationCleanerTest extends TestCase
{
    use EventDispatcherBehaviour;
    use KernelTestBehaviour;

    /**
     * @param array<string, mixed> $payloads
     * @param array<string> $allowed
     */
    #[DataProvider('cleanupCustomFieldsProvider')]
    public function testLineItemCustomFields(Cart $cart, array $payloads = [], array $allowed = []): void
    {
        $dispatcher = static::getContainer()->get('event_dispatcher');

        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->once())->method('__invoke');

        $this->addEventListener($dispatcher, CartBeforeSerializationEvent::class, $listener);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchFirstColumn')->willReturn($allowed);

        $cleaner = new CartSerializationCleaner($connection, $dispatcher);
        $cleaner->cleanupCart($cart);

        $items = $cart->getLineItems()->getFlat();
        foreach ($items as $item) {
            static::assertArrayHasKey($item->getId(), $payloads);
            static::assertSame($payloads[$item->getId()], $item->getPayload());
        }

        $delivery = $cart->getDeliveries()->first();
        $deliveryItems = $delivery !== null ? $delivery->getPositions()->getLineItems()->getFlat() : [];

        foreach ($deliveryItems as $item) {
            static::assertArrayHasKey($item->getId(), $payloads);
            static::assertSame($payloads[$item->getId()], $item->getPayload());
        }
    }

    #[DataProvider('cleanupCoversProvider')]
    public function testLineItemCovers(Cart $cart, ?MediaEntity $expectedCover): void
    {
        $dispatcher = static::createStub(EventDispatcher::class);
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchFirstColumn');

        $cleaner = new CartSerializationCleaner($connection, $dispatcher);
        $cleaner->cleanupCart($cart);

        $items = $cart->getLineItems()->getFlat();
        foreach ($items as $item) {
            static::assertEquals($expectedCover, $item->getCover());
        }
    }

    public static function cleanupCustomFieldsProvider(): \Generator
    {
        yield 'Test empty cart' => [
            new Cart('test'),
            [],
        ];

        yield 'Test strip payload' => [
            self::payloadCart('foo', ['customFields' => ['bar' => 1]]),
            ['foo' => ['customFields' => []], 'foo-child' => ['customFields' => []]],
        ];

        yield 'Test allowed field' => [
            self::payloadCart('foo', ['customFields' => ['bar' => 1]]),
            ['foo' => ['customFields' => ['bar' => 1]], 'foo-child' => ['customFields' => ['bar' => 1]]],
            ['bar'],
        ];

        yield 'Test multiple allowed fields' => [
            self::payloadCart('foo', ['customFields' => ['bar' => 1, 'baz' => 2]]),
            ['foo' => ['customFields' => ['bar' => 1, 'baz' => 2]], 'foo-child' => ['customFields' => ['bar' => 1, 'baz' => 2]]],
            ['bar', 'baz'],
        ];

        yield 'Test allowed field with unkown key' => [
            self::payloadCart('foo', ['customFields' => ['bar' => 1]]),
            ['foo' => ['customFields' => []], 'foo-child' => ['customFields' => []]],
            ['unknown_field'],
        ];
    }

    public static function cleanupCoversProvider(): \Generator
    {
        yield 'Test cover thumbnailRo cleanup' => [
            self::coverCart('foo', 'test'),
            self::coverItem('foo', '')->getCover(),
        ];

        yield 'Test cover thumbnailRo cleanup without ro data' => [
            self::coverCart('foo', null),
            self::coverItem('foo', null)->getCover(),
        ];

        yield 'Test cover thumbnailRo cleanup without cover' => [
            self::coverCart('foo', null, true),
            null,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function payloadItem(string $id, array $payload): LineItem
    {
        $item = new LineItem($id, 'foo');
        $item->setPayload($payload);

        $childItem = new LineItem($id . '-child', 'foo');
        $childItem->setPayload($payload);

        $item->addChild($childItem);

        return $item;
    }

    private static function coverItem(string $id, ?string $thumbnailString, bool $skipCover = false): LineItem
    {
        $item = new LineItem($id, 'foo');
        $childItem = new LineItem($id . 'child', 'foo');

        $item->addChild($childItem);

        if ($skipCover === true) {
            return $item;
        }

        $cover = new MediaEntity();
        if ($thumbnailString !== null) {
            $cover->setThumbnailsRo($thumbnailString);
        }

        $item->setCover($cover);

        $coverChild = new MediaEntity();
        if ($thumbnailString !== null) {
            $coverChild->setThumbnailsRo($thumbnailString);
        }

        $childItem->setCover($cover);

        return $item;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function payloadCart(string $id, array $payload): Cart
    {
        $cart = (new Cart('test'))->add(self::payloadItem($id, $payload));
        $cart->addDeliveries(self::itemDelivery(self::payloadItem($id, $payload)));

        return $cart;
    }

    private static function coverCart(string $id, ?string $thumbnailString, bool $skipCover = false): Cart
    {
        $cart = (new Cart('test'))->add(self::coverItem($id, $thumbnailString, $skipCover));
        $cart->addDeliveries(self::itemDelivery(self::coverItem($id, $thumbnailString, $skipCover)));

        return $cart;
    }

    private static function itemDelivery(LineItem $lineItem): DeliveryCollection
    {
        $delivery = new Delivery(
            new DeliveryPositionCollection(
                [
                    new DeliveryPosition(
                        $lineItem->getId(),
                        $lineItem,
                        1,
                        new CalculatedPrice(1.0, 1.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable())
                    ),
                ]
            ),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(1.0, 1.0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        return new DeliveryCollection([$delivery]);
    }
}
