<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\Event\PaymentOrderPaidEvent;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderReconciler;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Shopwell\Tests\Unit\Core\System\Payment\Order\_fixtures\PaymentOrderReconcilerFixture;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentOrderReconciler::class)]
class PaymentOrderReconcilerRecoveryTest extends TestCase
{
    public function testReconcileAnnouncesAnOrderThatWasPaidMeanwhile(): void
    {
        $paid = PaymentOrderReconcilerFixture::order(['paidAt' => new \DateTimeImmutable('2024-01-01T00:00:00+00:00'), 'stateId' => 'state-paid']);

        // the payment is already written, so the reconciler must not write it again - but the
        // platform may never have been told about it
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                PaymentOrderEntity::ENTITY_NAME,
                1,
                new PaymentOrderCollection([$paid]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ));
        $orderRepository->expects($this->never())->method('update');

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(PaymentOrderPaidEvent::class));

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(static function (array $data): bool {
                static::assertSame(PaymentWebhookDispatcher::EVENT_ORDER_PAID, $data[0]['event']);
                static::assertSame('order-id', $data[0]['paymentOrderId']);

                return true;
            }));
        $deliveryRepository->method('search')->willReturnOnConsecutiveCalls(
            PaymentOrderReconcilerFixture::deliverySearchResult(),
            PaymentOrderReconcilerFixture::deliverySearchResult(PaymentOrderReconcilerFixture::delivery())
        );

        PaymentOrderReconcilerFixture::reconciler($orderRepository, static::createStub(EntityRepository::class), $eventDispatcher, $deliveryRepository)
            ->reconcile($paid);
    }

    public function testReconcileLeavesAClosedOrExpiredOrderAlone(): void
    {
        $closed = PaymentOrderReconcilerFixture::order(['closedAt' => new \DateTimeImmutable('2024-01-01T00:00:00+00:00'), 'stateId' => 'state-closed']);

        // closed and expired orders are not payable either, but unlike a paid one there is nothing
        // left to announce - the order is not even read again
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->never())->method('search');
        $orderRepository->expects($this->never())->method('update');

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->never())->method('create');

        PaymentOrderReconcilerFixture::reconciler($orderRepository, static::createStub(EntityRepository::class), $eventDispatcher, $deliveryRepository)
            ->reconcile($closed);
    }
}
