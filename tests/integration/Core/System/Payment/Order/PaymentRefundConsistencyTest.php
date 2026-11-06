<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Order;

use Doctrine\DBAL\Connection;
use Payum\Core\GatewayInterface;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Shopwell\Core\System\Payment\Account\PaymentAccountCollection;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\Request\NotificationResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Notification\PaymentNotifyUrlBuilder;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptEntity;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Order\Event\PaymentEventDispatcher;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderRowLock;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\Payment\Order\PaymentRefundService;
use Shopwell\Core\System\Payment\Order\PaymentRefundStateHandler;
use Shopwell\Core\System\Payment\Order\PaymentRefundStates;
use Shopwell\Core\System\Payment\Order\Struct\PaymentRefundRequest;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDeliveryCollection;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;

/**
 * @internal
 */
#[Package('framework')]
class PaymentRefundConsistencyTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testAnUnansweredRefundKeepsItsReservationAndTheSameNumberIsSafeToReplay(): void
    {
        $orderId = $this->createOrder();
        $container = static::getContainer();
        $orders = $container->get(PaymentOrderService::class);
        $order = $orders->getById($orderId);
        $appRepository = $container->get('payment_app.repository');
        $app = $appRepository->search(new Criteria([$order->paymentAppId]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(PaymentAppEntity::class, $app);
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->once())->method('execute')->willThrowException(new \RuntimeException('response lost after acceptance'));
        $service = $this->refundServiceWithGateway($gateway, $orders);
        $request = new PaymentRefundRequest('lost-response', 600);

        try {
            $service->refund($app, $order, $request);
            static::fail('The lost response must be reported to the caller.');
        } catch (PaymentException $exception) {
            static::assertSame(502, $exception->getStatusCode());
        }

        $refund = $service->refund($app, $order, $request);
        static::assertNull($refund->refundedAt);
        static::assertSame($this->stateId(PaymentRefundStates::STATE_MACHINE, PaymentRefundStates::STATE_OPEN), $refund->stateId);
        static::assertSame(600, $container->get(PaymentOrderRowLock::class)->reservedRefundAmount($orderId));
        static::assertSame(0, $orders->getById($orderId)->refundedAmount);

        $this->expectExceptionObject(PaymentOrderException::refundAmountExceedsOrder($order->paymentNo, 500, 400));

        $service->refund($app, $order, new PaymentRefundRequest('another-refund', 500));
    }

    public function testApplyRefundUsesTheDatabaseAmountsInsteadOfTheStaleEntity(): void
    {
        $orderId = $this->createOrder();
        $orderService = static::getContainer()->get(PaymentOrderService::class);
        $stale = $orderService->getById($orderId);

        $orderService->applyRefund($stale, 400);
        $refunded = $orderService->applyRefund($stale, 600);

        static::assertSame(1000, $refunded->refundedAmount);
        static::assertSame(
            $this->stateId(PaymentOrderStates::STATE_MACHINE, PaymentOrderStates::STATE_REFUNDED),
            $refunded->stateId
        );
    }

    public function testDuplicateSuccessNotificationBooksOnceAndKeepsOneWebhook(): void
    {
        $orderId = $this->createOrder();
        $refundId = $this->createProcessingRefund($orderId, 400);
        $orderService = static::getContainer()->get(PaymentOrderService::class);
        $refundService = static::getContainer()->get(PaymentRefundService::class);
        $order = $orderService->getById($orderId);
        $refund = $refundService->getRefund($refundId);
        $notification = new NotificationResult(
            true,
            true,
            outRefundId: 'channel-refund-1',
            amount: 400,
            raw: ['status' => 'done']
        );

        $refundService->applyNotification($refund, $order, $notification);
        $refundService->applyNotification($refund, $order, $notification);

        $booked = $orderService->getById($orderId);
        static::assertSame(400, $booked->refundedAmount);
        static::assertSame(
            $this->stateId(PaymentOrderStates::STATE_MACHINE, PaymentOrderStates::STATE_REFUNDED_PARTIALLY),
            $booked->stateId
        );
        static::assertNotNull($refundService->getRefund($refundId)->refundedAt);

        /** @var EntityRepository<PaymentWebhookDeliveryCollection> $deliveryRepository */
        $deliveryRepository = static::getContainer()->get('payment_webhook_delivery.repository');
        $deliveries = $deliveryRepository->searchIds(
            (new Criteria())->addFilter(
                new EqualsFilter('paymentOrderId', $orderId),
                new EqualsFilter('event', PaymentWebhookDispatcher::EVENT_REFUND_FINISHED),
                new EqualsFilter('subject', $refundId)
            ),
            Context::createDefaultContext()
        );

        static::assertSame(1, $deliveries->getTotal());
    }

    private function refundServiceWithGateway(
        GatewayInterface $gateway,
        PaymentOrderService $orders,
    ): PaymentRefundService {
        $container = static::getContainer();
        $factory = static::createStub(PaymentGatewayFactory::class);
        $factory->method('createForPayment')->willReturn($gateway);
        $channel = new PaymentProduct(
            'alipay_h5',
            'alipay',
            'Alipay',
            [PaymentInteraction::REDIRECT],
            'alipay',
            capabilities: [PaymentCapability::REFUND, PaymentCapability::PARTIAL_REFUND],
        );
        $provider = new PaymentProvider(
            'alipay',
            'Alipay',
            [],
            ['alipay_h5' => $channel],
            $factory,
            static::createStub(PaymentNotificationExtractorInterface::class),
            capabilities: [PaymentCapability::REFUND, PaymentCapability::PARTIAL_REFUND],
        );
        $resolver = static::createStub(PaymentConfigResolver::class);
        $resolver->method('resolveForOrder')->willReturnCallback(static function (PaymentAppEntity $app, PaymentOrderEntity $order) use ($provider, $channel): ResolvedPaymentConfig {
            static::assertNotNull($order->activeAttempt);

            return new ResolvedPaymentConfig($provider, $channel, [], [], [], [], $order->activeAttempt->accountId);
        });

        return new PaymentRefundService(
            $container->get('payment_refund.repository'),
            $resolver,
            $orders,
            $container->get(PaymentEventDispatcher::class),
            $container->get(PaymentNotifyUrlBuilder::class),
            $container->get(PaymentRefundStateHandler::class),
            $container->get(InitialStateIdLoader::class),
            $container->get(ClockInterface::class),
            $container->get(AbstractNumberRangeValueGenerator::class),
            $container->get(PaymentOrderRowLock::class),
        );
    }

    private function createOrder(): string
    {
        $context = Context::createDefaultContext();
        $appId = Uuid::randomHex();

        /** @var EntityRepository<PaymentAppCollection> $appRepository */
        $appRepository = static::getContainer()->get('payment_app.repository');
        $appRepository->create([[
            'id' => $appId,
            'technicalName' => 'refund_consistency_' . bin2hex(random_bytes(6)),
            'name' => 'Payment refund consistency test app',
            'type' => PaymentAppEntity::TYPE_EXTERNAL,
            'appSecret' => static::getContainer()->get(PaymentCredentialCipher::class)->encryptValue('secret'),
            'active' => true,
        ]], $context);

        $orderId = Uuid::randomHex();

        /** @var EntityRepository<PaymentOrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('payment_order.repository');
        $orderRepository->create([[
            'id' => $orderId,
            'paymentNo' => 'PG' . strtoupper(bin2hex(random_bytes(8))),
            'outTradeNo' => 'OT' . strtoupper(bin2hex(random_bytes(8))),
            'paymentAppId' => $appId,
            'amount' => 1000,
            'paidAmount' => 1000,
            'refundedAmount' => 0,
            'currency' => 'CNY',
            'subject' => 'Payment refund consistency test',
            'stateId' => $this->stateId(PaymentOrderStates::STATE_MACHINE, PaymentOrderStates::STATE_PAID),
            'paidAt' => new \DateTimeImmutable(),
            'notifyUrl' => 'https://app.example.test/payment-webhook',
        ]], $context);

        $accountId = Uuid::randomHex();
        $attemptId = Uuid::randomHex();
        /** @var EntityRepository<PaymentAccountCollection> $accounts */
        $accounts = static::getContainer()->get('payment_account.repository');
        $accounts->create([[
            'id' => $accountId,
            'providerTechnicalName' => 'alipay',
            'name' => 'Original merchant account',
            'enabledProducts' => ['alipay_h5'],
            'active' => true,
            'ownerAppId' => $appId,
        ]], $context);
        /** @var EntityRepository<PaymentAttemptCollection> $attempts */
        $attempts = static::getContainer()->get('payment_attempt.repository');
        $attempts->create([[
            'id' => $attemptId,
            'paymentOrderId' => $orderId,
            'attemptNo' => 'p' . $attemptId,
            'accountId' => $accountId,
            'method' => 'alipay',
            'providerTechnicalName' => 'alipay',
            'channelTechnicalName' => 'alipay_h5',
            'interaction' => PaymentInteraction::REDIRECT,
            'status' => PaymentAttemptEntity::STATUS_PAID,
            'context' => [],
            'configuration' => ['params' => [], 'runtimeParams' => [], 'settings' => []],
        ]], $context);
        $orderRepository->update([['id' => $orderId, 'activeAttemptId' => $attemptId]], $context);

        return $orderId;
    }

    private function createProcessingRefund(string $orderId, int $amount): string
    {
        $refundId = Uuid::randomHex();

        /** @var EntityRepository<PaymentRefundCollection> $refundRepository */
        $refundRepository = static::getContainer()->get('payment_refund.repository');
        $refundRepository->create([[
            'id' => $refundId,
            'refundNo' => 'PR' . strtoupper(bin2hex(random_bytes(8))),
            'paymentOrderId' => $orderId,
            'paymentAttemptId' => static::getContainer()->get(PaymentOrderService::class)->getById($orderId)->activeAttemptId,
            'outRefundNo' => 'OR' . strtoupper(bin2hex(random_bytes(8))),
            'amount' => $amount,
            'currency' => 'CNY',
            'stateId' => $this->stateId(PaymentRefundStates::STATE_MACHINE, PaymentRefundStates::STATE_PROCESSING),
        ]], Context::createDefaultContext());

        return $refundId;
    }

    private function stateId(string $machine, string $state): string
    {
        $id = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(state.id)) FROM state_machine_state state
             INNER JOIN state_machine machine ON machine.id = state.state_machine_id
             WHERE machine.technical_name = :machine AND state.technical_name = :state',
            ['machine' => $machine, 'state' => $state]
        );

        static::assertIsString($id);

        return $id;
    }
}
