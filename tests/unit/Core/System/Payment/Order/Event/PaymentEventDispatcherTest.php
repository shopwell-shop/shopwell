<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Order\Event;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptEntity;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Order\Event\PaymentEventDispatcher;
use Shopwell\Core\System\Payment\Order\Event\PaymentOrderPaidEvent;
use Shopwell\Core\System\Payment\Order\Event\PaymentRefundFinishedEvent;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDeliveryCollection;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookSigner;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentEventDispatcher::class)]
class PaymentEventDispatcherTest extends TestCase
{
    public function testPaidEventIsAcknowledgedOnlyAfterEveryEffectSucceeded(): void
    {
        $order = $this->order();
        $orders = StaticEntityRepository::of(PaymentOrderCollection::class);
        $refunds = StaticEntityRepository::of(PaymentRefundCollection::class);
        $events = new EventDispatcher();
        $calls = 0;
        $events->addListener(PaymentOrderPaidEvent::class, static function () use (&$calls): void {
            if (++$calls === 1) {
                throw new \RuntimeException('wallet temporarily unavailable');
            }
        });
        $dispatcher = $this->dispatcher($orders, $refunds, $events);

        try {
            $dispatcher->dispatchPaid($order);
            static::fail('The failing consumer must remain retryable.');
        } catch (\RuntimeException $exception) {
            static::assertSame('wallet temporarily unavailable', $exception->getMessage());
        }
        static::assertArrayNotHasKey('eventDispatchedAt', $orders->updates[0][0]);

        $dispatcher->dispatchPaid($order);

        static::assertSame(2, $calls);
        static::assertSame($order->id, $orders->updates[2][0]['id']);
        static::assertEquals(new \DateTimeImmutable('2026-11-05'), $orders->updates[2][0]['eventDispatchedAt']);
    }

    public function testRecoveryContinuesAfterOneConsumerFailsAndRetriesRefundEvents(): void
    {
        $first = $this->order();
        $second = $this->order();
        $second->id = 'second-order';
        $second->setUniqueIdentifier('second-order');
        $refund = new PaymentRefundEntity();
        $refund->id = 'refund-id';
        $refund->setUniqueIdentifier('refund-id');
        $refund->paymentOrder = $second;
        $refund->paymentOrderId = $second->id;
        $refund->refundNo = 'refund-no';
        $refund->outRefundNo = 'merchant-refund';
        $refund->amount = 100;
        $refund->currency = 'CNY';
        $refund->refundedAt = new \DateTimeImmutable('2026-11-05');
        $orders = new StaticEntityRepository([new PaymentOrderCollection([$first, $second])]);
        $refunds = new StaticEntityRepository([new PaymentRefundCollection([$refund])]);
        $events = new EventDispatcher();
        $events->addListener(PaymentOrderPaidEvent::class, static function (PaymentOrderPaidEvent $event) use ($first): void {
            if ($event->getPaymentOrder() === $first) {
                throw new \RuntimeException('one failed consumer');
            }
        });
        $finished = [];
        $events->addListener(PaymentRefundFinishedEvent::class, static function (PaymentRefundFinishedEvent $event) use (&$finished): void {
            $finished[] = $event->getPaymentRefund()->id;
        });

        static::assertSame(2, $this->dispatcher($orders, $refunds, $events)->dispatchPending());
        static::assertSame(['second-order'], array_column(array_filter($orders->getPayloads(StaticEntityRepository::UPDATE), static fn (array $write): bool => isset($write['eventDispatchedAt'])), 'id'));
        static::assertSame(['refund-id'], $finished);
        static::assertCount(2, $refunds->updates);
        static::assertSame(100, $orders->searchedCriteria[0]->getLimit());
        static::assertCount(2, $orders->searchedCriteria[0]->getFilters());
        static::assertCount(2, $refunds->searchedCriteria[0]->getFilters());
    }

    /**
     * @param StaticEntityRepository<PaymentOrderCollection> $orders
     * @param StaticEntityRepository<PaymentRefundCollection> $refunds
     */
    private function dispatcher(StaticEntityRepository $orders, StaticEntityRepository $refunds, EventDispatcher $events): PaymentEventDispatcher
    {
        $clock = new MockClock('2026-11-05');
        $webhooks = new PaymentWebhookDispatcher(
            StaticEntityRepository::of(PaymentWebhookDeliveryCollection::class),
            StaticEntityRepository::of(PaymentAppCollection::class),
            new PaymentWebhookSigner(new PaymentSignatureService(), new PaymentCredentialCipher('test'), $clock),
            static::createStub(HttpClientInterface::class),
            $clock,
            new NullLogger(),
            static::createStub(Connection::class),
        );

        return new PaymentEventDispatcher($orders, $refunds, $webhooks, $events, $clock, new NullLogger());
    }

    private function order(): PaymentOrderEntity
    {
        $order = new PaymentOrderEntity();
        $order->id = 'order-id';
        $order->setUniqueIdentifier('order-id');
        $order->paymentNo = 'payment-no';
        $order->paidAt = new \DateTimeImmutable('2026-11-05');
        $order->paidAmount = 1000;
        $order->refundedAmount = 100;
        $order->currency = 'CNY';
        $order->activeAttempt = new PaymentAttemptEntity();
        $order->activeAttempt->providerTechnicalName = 'alipay';
        $order->activeAttempt->channelTechnicalName = 'alipay_web';

        return $order;
    }
}
