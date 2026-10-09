<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\AbstractCartPersister;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\CartCompressor;
use Shopwell\Core\Checkout\Cart\CartPersister;
use Shopwell\Core\Checkout\Cart\CartSerializationCleaner;
use Shopwell\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopwell\Core\Checkout\Cart\Event\CartSavedEvent;
use Shopwell\Core\Checkout\Cart\Event\CartVerifyPersistEvent;
use Shopwell\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\CheckoutPermissions;
use Shopwell\Core\Content\Product\Cart\ProductNotFoundError;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Assert\Serialization;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
class CartPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testLoadWithNotExistingToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);
        $eventDispatcher = new EventDispatcher();
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $persister = new CartPersister($connection, $eventDispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), new NativeClock());

        $e = null;

        try {
            $persister->load('not_existing_token', Generator::generateSalesChannelContext());
        } catch (\Exception $e) {
        }

        static::assertInstanceOf(CartTokenNotFoundException::class, $e);
        static::assertSame('not_existing_token', $e->getParameter('token'));
    }

    public function testLoadWithExistingToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);
        $eventDispatcher = new EventDispatcher();
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(
                ['payload' => serialize(new Cart('existing')), 'rule_ids' => json_encode([]), 'compressed' => 0]
            );

        $persister = new CartPersister($connection, $eventDispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), new NativeClock());
        $cart = $persister->load('existing', Generator::generateSalesChannelContext());

        $expected = new Cart('existing');
        $expected->setPersisted(true);

        static::assertEquals($expected, $cart);
    }

    public function testEmptyCartShouldNotBeSaved(): void
    {
        $connection = $this->createMock(Connection::class);
        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);

        $eventDispatcher = new EventDispatcher();

        // Cart should be deleted (in case it exists).
        // Cart should not be inserted or updated.
        $this->expectSqlQuery($connection, 'DELETE FROM `cart`');

        $persister = new CartPersister($connection, $eventDispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), new NativeClock());

        $cart = new Cart('existing');

        $persister->save($cart, Generator::generateSalesChannelContext());
    }

    public function testEmptyCartWithManualShippingCostsExtensionIsSaved(): void
    {
        $cart = new Cart('existing');
        $cart->addExtension(
            DeliveryProcessor::MANUAL_SHIPPING_COSTS,
            new CalculatedPrice(
                20.0,
                20.0,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            )
        );

        static::getContainer()->get(CartPersister::class)
            ->save($cart, $this->getSalesChannelContext($cart->getToken()));

        $token = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT token FROM cart WHERE token = :token', ['token' => $cart->getToken()]);

        static::assertNotFalse($token);
    }

    public function testEmptyCartWithCustomerCommentIsSaved(): void
    {
        $cart = new Cart('existing');
        $cart->setCustomerComment('Foo');

        static::getContainer()->get(CartPersister::class)
            ->save($cart, $this->getSalesChannelContext($cart->getToken()));

        $token = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT token FROM cart WHERE token = :token', ['token' => $cart->getToken()]);

        static::assertNotFalse($token);
    }

    public function testSaveWithItems(): void
    {
        $cart = new Cart('existing');
        $cart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        static::getContainer()->get(CartPersister::class)
            ->save($cart, $this->getSalesChannelContext($cart->getToken()));

        $token = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT token FROM cart WHERE token = :token', ['token' => $cart->getToken()]);

        static::assertNotFalse($token);
    }

    public function testSavingExistingCartDoesNotRecreateDeletedCart(): void
    {
        $cart = new Cart('existing');
        $cart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        $persister = static::getContainer()->get(CartPersister::class);
        $context = $this->getSalesChannelContext($cart->getToken());

        $persister->save($cart, $context);
        $persister->delete($cart->getToken(), $context);
        $persister->save($cart, $context);

        $token = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT token FROM cart WHERE token = :token', ['token' => $cart->getToken()]);

        static::assertFalse($token);
    }

    public function testExistsReflectsStoredCart(): void
    {
        $cart = new Cart('existing');
        $cart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        $persister = static::getContainer()->get(CartPersister::class);
        $context = $this->getSalesChannelContext($cart->getToken());

        static::assertFalse($persister->exists($cart->getToken(), $context));

        $persister->save($cart, $context);

        static::assertTrue($persister->exists($cart->getToken(), $context));

        $persister->delete($cart->getToken(), $context);

        static::assertFalse($persister->exists($cart->getToken(), $context));
    }

    public function testRetokenizedCartIsInsertedUnderTheNewToken(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $cart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        $persister = static::getContainer()->get(CartPersister::class);
        $persister->save($cart, $this->getSalesChannelContext($cart->getToken()));

        $loaded = $persister->load($cart->getToken(), $this->getSalesChannelContext($cart->getToken()));

        // extensions hand a persisted cart a new token and store it elsewhere, e.g. as a quote payload
        $detachedToken = Uuid::randomHex();
        $loaded->setToken($detachedToken);

        $detached = Serialization::assertUnserializedInstanceOf(Cart::class, serialize($loaded));
        static::assertInstanceOf(Cart::class, $detached);

        $persister->save($detached, $this->getSalesChannelContext($detachedToken));

        $connection = static::getContainer()->get(Connection::class);

        static::assertSame($detachedToken, $connection->fetchOne(
            'SELECT token FROM cart WHERE token = :token',
            ['token' => $detachedToken]
        ));
        static::assertSame($cart->getToken(), $connection->fetchOne(
            'SELECT token FROM cart WHERE token = :token',
            ['token' => $cart->getToken()]
        ));
    }

    public function testRecalculatingARetokenizedCartWritesItUnderTheNewToken(): void
    {
        $cart = new Cart(Uuid::randomHex());
        $cart->add(
            (new LineItem('A', LineItem::CUSTOM_LINE_ITEM_TYPE))
                ->setPriceDefinition(new QuantityPriceDefinition(10.0, new TaxRuleCollection()))
                ->setLabel('test')
        );

        $persister = static::getContainer()->get(CartPersister::class);
        $persister->save($cart, $this->getSalesChannelContext($cart->getToken()));

        $loaded = $persister->load($cart->getToken(), $this->getSalesChannelContext($cart->getToken()));

        $newToken = Uuid::randomHex();
        $loaded->setToken($newToken);

        // Processor::process() carries the persisted state over, so a stale one loses the write here
        static::getContainer()->get(CartService::class)
            ->recalculate($loaded, $this->getSalesChannelContext($newToken));

        static::assertSame($newToken, static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT token FROM cart WHERE token = :token',
            ['token' => $newToken]
        ));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    public function testRecalculationCartShouldNotBeSaved(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $cartBehavior = new CartBehavior([], true, true);

        $cart = new Cart('existing');
        $cart->setBehavior($cartBehavior);
        $cart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        static::getContainer()->get(CartPersister::class)
            ->save($cart, $this->getSalesChannelContext($cart->getToken()));

        $token = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT token FROM cart WHERE token = :token', ['token' => $cart->getToken()]);

        static::assertFalse($token);
    }

    public function testSkipPersistenceCartShouldNotBeSaved(): void
    {
        $cartBehavior = new CartBehavior([CheckoutPermissions::SKIP_CART_PERSISTENCE => true], true);

        $cart = new Cart('existing');
        $cart->setBehavior($cartBehavior);
        $cart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        static::getContainer()->get(CartPersister::class)
            ->save($cart, $this->getSalesChannelContext($cart->getToken()));

        $token = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT token FROM cart WHERE token = :token', ['token' => $cart->getToken()]);

        static::assertFalse($token);
    }

    public function testCartSavedEventIsFired(): void
    {
        $eventDispatcher = static::getContainer()->get('event_dispatcher');

        $caughtEvent = null;
        $this->addEventListener($eventDispatcher, CartSavedEvent::class, static function (CartSavedEvent $event) use (&$caughtEvent): void {
            $caughtEvent = $event;
        });

        $cart = new Cart('existing');
        $cart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        static::getContainer()->get(CartPersister::class)
            ->save($cart, $this->getSalesChannelContext($cart->getToken()));

        $token = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT token FROM cart WHERE token = :token', ['token' => $cart->getToken()]);

        static::assertNotFalse($token);

        static::assertInstanceOf(CartSavedEvent::class, $caughtEvent);
        static::assertCount(1, $caughtEvent->getCart()->getLineItems());
        $firstLineItem = $caughtEvent->getCart()->getLineItems()->first();
        static::assertInstanceOf(LineItem::class, $firstLineItem);
        static::assertSame('test', $firstLineItem->getLabel());
    }

    public function testCartCanBeUnserialized(): void
    {
        Serialization::assertUnserializedInstanceOf(Cart::class, (string) file_get_contents(__DIR__ . '/fixtures/cart.blob'));
    }

    public function testCartVerifyPersistEventIsFiredAndNotPersisted(): void
    {
        $connection = $this->createMock(Connection::class);
        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);
        $eventDispatcher = new EventDispatcher();

        $this->expectSqlQuery($connection, 'DELETE FROM `cart`');

        $caughtEvent = null;
        $this->addEventListener($eventDispatcher, CartVerifyPersistEvent::class, static function (CartVerifyPersistEvent $event) use (&$caughtEvent): void {
            $caughtEvent = $event;
        });

        $persister = new CartPersister($connection, $eventDispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), new NativeClock());

        $cart = new Cart('existing');

        $persister->save(
            $cart,
            $this->getSalesChannelContext($cart->getToken())
        );
        static::assertInstanceOf(CartVerifyPersistEvent::class, $caughtEvent, CartVerifyPersistEvent::class . ' did not run');
        static::assertFalse($caughtEvent->shouldBePersisted());
        static::assertCount(0, $caughtEvent->getCart()->getLineItems());
    }

    public function testCartVerifyPersistEventIsFiredAndPersisted(): void
    {
        $caughtEvent = null;
        $this->addEventListener(static::getContainer()->get('event_dispatcher'), CartVerifyPersistEvent::class, static function (CartVerifyPersistEvent $event) use (&$caughtEvent): void {
            $caughtEvent = $event;
        });

        $cart = new Cart('existing');
        $cart->addLineItems(new LineItemCollection([
            new LineItem(Uuid::randomHex(), LineItem::PROMOTION_LINE_ITEM_TYPE, Uuid::randomHex(), 1),
        ]));

        static::getContainer()->get(CartPersister::class)
            ->save($cart, $this->getSalesChannelContext($cart->getToken()));

        $token = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT token FROM cart WHERE token = :token', ['token' => $cart->getToken()]);

        static::assertNotFalse($token);

        static::assertInstanceOf(CartVerifyPersistEvent::class, $caughtEvent);
        static::assertTrue($caughtEvent->shouldBePersisted());
        static::assertCount(1, $caughtEvent->getCart()->getLineItems());
    }

    public function testCartVerifyPersistEventIsFiredAndModified(): void
    {
        $caughtEvent = null;
        $this->addEventListener(static::getContainer()->get('event_dispatcher'), CartVerifyPersistEvent::class, static function (CartVerifyPersistEvent $event) use (&$caughtEvent): void {
            $caughtEvent = $event;
            $event->setShouldPersist(false);
        });

        $cart = new Cart('existing');
        $cart->addLineItems(new LineItemCollection([
            new LineItem(Uuid::randomHex(), LineItem::PROMOTION_LINE_ITEM_TYPE, Uuid::randomHex(), 1),
        ]));

        static::getContainer()->get(CartPersister::class)
            ->save($cart, $this->getSalesChannelContext($cart->getToken()));

        $token = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT token FROM cart WHERE token = :token', ['token' => $cart->getToken()]);

        static::assertFalse($token);

        static::assertInstanceOf(CartVerifyPersistEvent::class, $caughtEvent);
        static::assertFalse($caughtEvent->shouldBePersisted());
        static::assertCount(1, $caughtEvent->getCart()->getLineItems());
    }

    public function testPrune(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM cart');

        $ids = new IdsCollection();

        $now = new \DateTimeImmutable();

        $this->createCart($ids->create('cart-1'), $now);

        $expiredDate1 = $now->modify(\sprintf('-%d day', 121));
        $this->createCart($ids->create('cart-2'), $expiredDate1);

        $this->createCart($ids->create('cart-3'), $expiredDate1, $now);

        $expiredDate2 = $now->modify(\sprintf('-%d day', 122));
        $this->createCart($ids->create('cart-4'), $expiredDate2, $expiredDate1);

        static::getContainer()->get(CartPersister::class)->prune(30);

        $carts = static::getContainer()->get(Connection::class)
            ->fetchFirstColumn('SELECT token FROM cart');

        static::assertCount(2, $carts);
        static::assertContains($ids->get('cart-1'), $carts);
        static::assertContains($ids->get('cart-3'), $carts);
    }

    public function testSaveCartWithoutErrorCleanup(): void
    {
        $cart = new Cart('existing');
        $cart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        $cart->addErrors(new ProductNotFoundError(Uuid::randomHex()));

        $context = $this->getSalesChannelContext($cart->getToken());
        $cartPersister = static::getContainer()->get(CartPersister::class);
        $cartPersister->save($cart, $context);

        $cart = $cartPersister->load($cart->getToken(), $context);

        static::assertNotCount(0, $cart->getLineItems());
        static::assertCount(0, $cart->getErrors());
    }

    public function testSaveCartWithPersistCartErrorPermission(): void
    {
        $cart = new Cart('existing');
        $cart->add(
            (new LineItem('A', 'test'))
                ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('test')
        );

        $cart->setBehavior(new CartBehavior([
            AbstractCartPersister::PERSIST_CART_ERROR_PERMISSION => true,
        ]));

        $productId = Uuid::randomHex();
        $cart->addErrors(new ProductNotFoundError($productId));

        $context = $this->getSalesChannelContext($cart->getToken());
        $cartPersister = static::getContainer()->get(CartPersister::class);
        $cartPersister->save($cart, $context);

        $cart = $cartPersister->load($cart->getToken(), $context);

        static::assertNotCount(0, $cart->getLineItems());
        static::assertNotCount(0, $cart->getErrors());

        $error = $cart->getErrors()->first();
        static::assertInstanceOf(ProductNotFoundError::class, $error);
        static::assertEquals(['id' => $productId], $error->getParameters());
    }

    private function getSalesChannelContext(string $token): SalesChannelContext
    {
        return static::getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create($token, TestDefaults::SALES_CHANNEL);
    }

    private function expectSqlQuery(MockObject $connection, string $beginOfSql): void
    {
        $connection->expects($this->once())
            ->method('prepare')
            ->with(
                static::callback(static fn (string $sql): bool => \str_starts_with(\trim($sql), $beginOfSql))
            )
            ->willReturnCallback(static fn (string $sql): Statement => static::getContainer()->get(Connection::class)->prepare($sql));
    }

    private function createCart(string $token, \DateTimeImmutable $date, ?\DateTimeImmutable $updatedAt = null): void
    {
        $cart = [
            'token' => $token,
            'payload' => '',
            'rule_ids' => json_encode([]),
            'created_at' => $updatedAt?->format(Defaults::STORAGE_DATE_TIME_FORMAT) ?? $date->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        static::getContainer()->get(Connection::class)
            ->insert('cart', $cart);
    }
}
