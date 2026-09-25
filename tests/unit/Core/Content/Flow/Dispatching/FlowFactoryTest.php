<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Flow\Dispatching;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Content\Flow\Dispatching\FlowFactory;
use Shopwell\Core\Content\Flow\Dispatching\Storer\OrderStorer;
use Shopwell\Core\Content\Shared\MailFlow\DataProvider\OrderProvider;
use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(FlowFactory::class)]
class FlowFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $ids = new IdsCollection();
        $order = new OrderEntity();
        $order->setId($ids->get('orderId'));

        $context = Generator::generateSalesChannelContext();

        $awareEvent = new CheckoutOrderPlacedEvent($context, $order);

        $orderStorer = new OrderStorer(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(OrderProvider::class),
        );
        $flowFactory = new FlowFactory([$orderStorer]);
        $flow = $flowFactory->create($awareEvent);

        static::assertSame($ids->get('orderId'), $flow->getStore('orderId'));
        static::assertInstanceOf(SystemSource::class, $flow->getContext()->getSource());
        static::assertSame(Context::SYSTEM_SCOPE, $flow->getContext()->getScope());
    }

    public function testRestore(): void
    {
        $ids = new IdsCollection();
        $order = new OrderEntity();
        $order->setId($ids->get('orderId'));

        $orderProvider = static::createStub(OrderProvider::class);
        $orderProvider->method('getData')->willReturn($order);

        $context = Generator::generateSalesChannelContext();

        $awareEvent = new CheckoutOrderPlacedEvent($context, $order);

        $orderStorer = new OrderStorer(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            $orderProvider,
        );
        $flowFactory = new FlowFactory([$orderStorer]);

        $storedData = [
            'orderId' => $ids->get('orderId'),
            'additional_keys' => ['order'],
        ];
        $flow = $flowFactory->restore('checkout.order.placed', $awareEvent->getContext(), $storedData);

        static::assertInstanceOf(OrderEntity::class, $flow->getData('order'));
        static::assertSame($ids->get('orderId'), $flow->getData('order')->getId());

        static::assertInstanceOf(SystemSource::class, $flow->getContext()->getSource());
        static::assertSame(Context::SYSTEM_SCOPE, $flow->getContext()->getScope());
    }
}
