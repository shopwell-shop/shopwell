<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Order\Event;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Order\Event\PaymentEventDispatcher;
use Shopwell\Core\System\Payment\Order\Event\PaymentOrderPaidEvent;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderPaidHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
class PaymentEventDispatcherTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testCommittedPaymentSurvivesAConsumerFailureAndIsRecoveredWithoutAnotherCallback(): void
    {
        $container = static::getContainer();
        $connection = $container->get(Connection::class);
        $appId = $connection->fetchOne('SELECT LOWER(HEX(id)) FROM payment_app WHERE type = :type LIMIT 1', ['type' => PaymentAppEntity::TYPE_PLATFORM]);
        static::assertIsString($appId);
        $stateId = $connection->fetchOne('SELECT LOWER(HEX(state.id)) FROM state_machine_state state INNER JOIN state_machine machine ON machine.id = state.state_machine_id WHERE machine.technical_name = :machine AND state.technical_name = :state', ['machine' => PaymentOrderStates::STATE_MACHINE, 'state' => PaymentOrderStates::STATE_OPENED]);
        static::assertIsString($stateId);

        /** @var EntityRepository<PaymentOrderCollection> $orders */
        $orders = $container->get('payment_order.repository');
        /** @var EntityRepository<PaymentRefundCollection> $refunds */
        $refunds = $container->get('payment_refund.repository');
        $id = Uuid::randomHex();
        $orders->create([[
            'id' => $id,
            'paymentNo' => 'PG' . $id,
            'outTradeNo' => $id,
            'paymentAppId' => $appId,
            'accountAppId' => $appId,
            'providerTechnicalName' => 'alipay',
            'channelTechnicalName' => 'alipay_web',
            'amount' => 1000,
            'paidAmount' => 0,
            'refundedAmount' => 0,
            'currency' => 'CNY',
            'subject' => 'Durable settlement event',
            'stateId' => $stateId,
        ]], Context::createDefaultContext());
        $calls = 0;
        $events = new EventDispatcher();
        $events->addListener(PaymentOrderPaidEvent::class, static function (PaymentOrderPaidEvent $event) use (&$calls, $id): void {
            if ($event->getPaymentOrder()->id === $id && ++$calls === 1) {
                throw new \RuntimeException('consumer interrupted');
            }
        });
        $dispatcher = new PaymentEventDispatcher($orders, $refunds, $container->get(PaymentWebhookDispatcher::class), $events, new MockClock('2026-11-05'), new NullLogger());
        $orderService = $container->get(PaymentOrderService::class);
        $handler = new PaymentOrderPaidHandler($orderService, $dispatcher);

        try {
            $handler->handle($orderService->getById($id), 1000, 'channel-trade', []);
            static::fail('The event consumer must fail once.');
        } catch (\RuntimeException $exception) {
            static::assertSame('consumer interrupted', $exception->getMessage());
        }

        $paid = $orderService->getById($id);
        static::assertInstanceOf(\DateTimeInterface::class, $paid->paidAt);
        static::assertSame(1000, $paid->paidAmount);
        static::assertNull($paid->eventDispatchedAt);
        static::assertInstanceOf(\DateTimeInterface::class, $paid->eventDispatchAttemptedAt);

        $dispatcher->dispatchPending();
        static::assertSame(2, $calls);
        static::assertInstanceOf(\DateTimeInterface::class, $orderService->getById($id)->eventDispatchedAt);
        static::assertSame(0, $dispatcher->dispatchPending());
    }
}
