<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Order;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartException;
use Shopwell\Core\Checkout\Cart\CartSerializationCleaner;
use Shopwell\Core\Checkout\Cart\Error\GenericCartError;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopwell\Core\Checkout\Cart\Order\OrderConverter;
use Shopwell\Core\Checkout\Cart\Order\OrderPersister;
use Shopwell\Core\Checkout\Order\Exception\EmptyCartException;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderPersister::class)]
class OrderPersisterTest extends TestCase
{
    public function testPersist(): void
    {
        $context = Generator::generateSalesChannelContext();

        $cart = new Cart('hatoken');
        $cart->add(new LineItem('hatoken', 'product'));

        $order = new OrderEntity();
        $order->assign([
            'id' => 'test-id',
        ]);

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects($this->once())
            ->method('convertToOrder')
            ->with($cart, $context, static::equalTo(new OrderConversionContext()))
            ->willReturn(['id' => $order->getId()]);

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects($this->once())
            ->method('create')
            ->with([['id' => $order->getId()]], $context->getContext());

        $persister = new OrderPersister($repo, $orderConverter, static::createStub(CartSerializationCleaner::class));
        $id = $persister->persist($cart, $context);

        static::assertSame('test-id', $id);
    }

    public function testWithBlockingCart(): void
    {
        $context = Generator::generateSalesChannelContext();

        $cart = new Cart('hatoken');
        $cart->add(new LineItem('hatoken', 'product'));
        $cart->addErrors(
            new GenericCartError(
                'test',
                'test',
                [],
                1,
                true,
                true,
                true
            )
        );

        $persister = new OrderPersister(
            static::createStub(EntityRepository::class),
            static::createStub(OrderConverter::class),
            static::createStub(CartSerializationCleaner::class),
        );

        $this->expectException(CartException::class);

        $persister->persist($cart, $context);
    }

    public function testPersistWithoutCustomer(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->assign(['customer' => null]);

        $cart = new Cart('hatoken');
        $cart->add(new LineItem('hatoken', 'product'));

        $persister = new OrderPersister(
            static::createStub(EntityRepository::class),
            static::createStub(OrderConverter::class),
            static::createStub(CartSerializationCleaner::class),
        );

        $this->expectExceptionObject(CartException::customerNotLoggedIn());

        $persister->persist($cart, $context);
    }

    public function testPersistWithEmptyCart(): void
    {
        $context = Generator::generateSalesChannelContext();

        $cart = new Cart('hatoken');

        $persister = new OrderPersister(
            static::createStub(EntityRepository::class),
            static::createStub(OrderConverter::class),
            static::createStub(CartSerializationCleaner::class),
        );

        $this->expectExceptionObject(new EmptyCartException());

        $persister->persist($cart, $context);
    }

    public function testPersistWithCartCleaner(): void
    {
        $context = Generator::generateSalesChannelContext();

        $lineItem = new LineItem('hatoken', 'product');
        $lineItem->setPayloadValue('customFields', ['test' => 'test']);

        $cart = new Cart('hatoken');
        $cart->add($lineItem);

        static::assertNotNull($cart->getLineItems()->first());

        static::assertSame($cart->getLineItems()->first()->getPayloadValue('customFields'), ['test' => 'test']);

        $order = new OrderEntity();
        $order->assign([
            'id' => 'test-id',
        ]);

        $cartSerializationCleaner = new CartSerializationCleaner(
            static::createStub(Connection::class),
            static::createStub(EventDispatcherInterface::class)
        );

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects($this->once())
            ->method('convertToOrder')
            ->willReturn(['id' => $order->getId()]);

        $persister = new OrderPersister(
            static::createStub(EntityRepository::class),
            $orderConverter,
            $cartSerializationCleaner,
        );

        $persister->persist($cart, $context);

        static::assertNotNull($cart->getLineItems()->first());

        static::assertSame($cart->getLineItems()->first()->getPayloadValue('customFields'), []);
    }
}
