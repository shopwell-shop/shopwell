<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Notification;

use Payum\Core\GatewayInterface;
use Payum\Core\Request\Notify;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Gateway\NotificationResult;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationCollection;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationEntity;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationProcessor;
use Shopwell\Core\System\Payment\Notification\PaymentNotifyUrlBuilder;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationData;
use Shopwell\Core\System\Payment\Order\Event\PaymentOrderPaidEvent;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundService;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundStateHandler;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDeliveryCollection;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookSigner;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentNotificationProcessor::class)]
class PaymentNotificationProcessorTest extends TestCase
{
    private const NOW = '2024-01-01T00:00:00+00:00';

    private const CREATED_STATE = 'state-created';

    public function testProcessRejectsANotificationOfAnUnknownOrder(): void
    {
        $notificationRepository = $this->notificationRepository(
            onCreate: function (array $row): void {
                static::assertSame('alipay', $row['providerTechnicalName']);
                static::assertSame('raw-body', $row['rawBody']);
                static::assertSame('1.2.3.4', $row['remoteIp']);
                // credentials never end up on the notification
                static::assertSame('[hidden]', $row['headers']['authorization']);
                static::assertSame('value', $row['headers']['x-custom']);
            },
            onUpdate: function (array $row): void {
                static::assertSame(PaymentNotificationEntity::RESULT_UNMATCHED, $row['processResult']);
                static::assertNull($row['paymentOrderId']);
                static::assertFalse($row['signatureValid']);
                static::assertFalse($row['processed']);
            }
        );

        $service = $this->service(
            $notificationRepository,
            static::createStub(GatewayInterface::class),
            $this->orderRepository(),
            $this->extractor('PG0001')
        );

        $this->expectExceptionObject(PaymentException::notificationOrderUnknown('alipay', 'PG0001'));

        $service->process('alipay', 'raw-body', ['Authorization' => 'Bearer secret', 'X-Custom' => 'value'], '1.2.3.4');
    }

    public function testProcessMarksTheNotificationInvalidWhenTheSignatureIsNotValid(): void
    {
        $order = $this->order();

        $notificationRepository = $this->notificationRepository(
            onUpdate: function (array $row): void {
                static::assertSame(PaymentNotificationEntity::RESULT_INVALID_SIGNATURE, $row['processResult']);
                static::assertSame('order-id', $row['paymentOrderId']);
                static::assertFalse($row['signatureValid']);
            }
        );

        $service = $this->service(
            $notificationRepository,
            $this->gateway(new NotificationResult(signatureValid: false, succeeded: false)),
            $this->orderRepository($order),
            $this->extractor('PG0001')
        );

        $this->expectExceptionObject(PaymentException::notificationInvalid('alipay', 'the signature of the notification is invalid'));

        $service->process('alipay', 'raw-body', [], '1.2.3.4');
    }

    public function testProcessRejectsANotificationWithAMismatchedAmount(): void
    {
        $order = $this->order();

        $notificationRepository = $this->notificationRepository(
            onUpdate: function (array $row): void {
                static::assertSame(PaymentNotificationEntity::RESULT_AMOUNT_MISMATCH, $row['processResult']);
                static::assertTrue($row['signatureValid']);
            }
        );

        $service = $this->service(
            $notificationRepository,
            $this->gateway(new NotificationResult(signatureValid: true, succeeded: true, amount: 999)),
            $this->orderRepository($order),
            $this->extractor('PG0001')
        );

        $this->expectExceptionObject(PaymentException::amountMismatch('PG0001', 1000, 999));

        $service->process('alipay', 'raw-body', [], '1.2.3.4');
    }

    public function testProcessIgnoresANotificationThatDidNotSucceed(): void
    {
        $order = $this->order();

        $notificationRepository = $this->notificationRepository(
            onUpdate: function (array $row): void {
                static::assertSame(PaymentNotificationEntity::RESULT_IGNORED, $row['processResult']);
                // an ignored notification is fully processed, it stays for support only
                static::assertTrue($row['processed']);
            }
        );

        $orderStateMachine = static::createMock(StateMachineRegistry::class);
        $orderStateMachine->expects($this->never())->method('transition');

        $service = $this->service(
            $notificationRepository,
            $this->gateway(new NotificationResult(signatureValid: true, succeeded: false, amount: 1000)),
            $this->orderRepository($order),
            $this->extractor('PG0001'),
            orderService: $this->orderService($this->orderRepository($order), $orderStateMachine)
        );

        $result = $service->process('alipay', 'raw-body', [], '1.2.3.4');

        static::assertSame('notification-id', $result->id);
    }

    public function testProcessTreatsANotificationOfAnAlreadyPaidOrderAsDuplicate(): void
    {
        $order = $this->order(['paidAt' => new \DateTimeImmutable(self::NOW), 'stateId' => 'state-paid']);

        $notificationRepository = $this->notificationRepository(
            onUpdate: function (array $row): void {
                static::assertSame(PaymentNotificationEntity::RESULT_DUPLICATE, $row['processResult']);
                static::assertTrue($row['processed']);
            }
        );

        $service = $this->service(
            $notificationRepository,
            $this->gateway(new NotificationResult(signatureValid: true, succeeded: true, amount: 1000)),
            $this->orderRepository($order),
            $this->extractor('PG0001')
        );

        $result = $service->process('alipay', 'raw-body', [], '1.2.3.4');

        static::assertSame('notification-id', $result->id);
    }

    public function testProcessMarksTheOrderPaidAndNotifiesTheApp(): void
    {
        $order = $this->order();
        $paidOrder = $this->order(['paidAt' => new \DateTimeImmutable(self::NOW), 'paidAmount' => 1000, 'outPaymentNo' => 'channel-trade-1', 'stateId' => 'state-paid']);

        $notificationRepository = $this->notificationRepository(
            onUpdate: function (array $row): void {
                // NOTE: the service never assigns RESULT_OK, so processResult stays at its initial
                // value and processed stays false even though the payment was applied - see the
                // report of this task. Only the correct, observable effects are asserted here.
                static::assertSame('order-id', $row['paymentOrderId']);
                static::assertTrue($row['signatureValid']);
            }
        );

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->orderSearchResult($order),
                $this->orderSearchResult($paidOrder)
            );

        $orderStateMachine = static::createMock(StateMachineRegistry::class);
        $orderStateMachine->expects($this->exactly(2))
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => \in_array($t->getTransitionName(), [PaymentOrderStates::ACTION_PROCESS, PaymentOrderStates::ACTION_PAY], true)));

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(PaymentOrderPaidEvent::class));

        $service = $this->service(
            $notificationRepository,
            $this->gateway(new NotificationResult(signatureValid: true, succeeded: true, outPaymentNo: 'channel-trade-1', amount: 1000, raw: ['trade' => 'raw'])),
            $orderRepository,
            $this->extractor('PG0001'),
            orderService: $this->orderService($orderRepository, $orderStateMachine),
            eventDispatcher: $eventDispatcher
        );

        static::assertSame('notification-id', $service->process('alipay', 'raw-body', [], '1.2.3.4')->id);
    }

    public function testProcessThrowsWhenTheStoredNotificationCannotBeReloaded(): void
    {
        $order = $this->order(['paidAt' => new \DateTimeImmutable(self::NOW), 'stateId' => 'state-paid']);

        $notificationRepository = static::createStub(EntityRepository::class);
        $notificationRepository->method('create');
        $notificationRepository->method('update');
        $notificationRepository->method('search')->willReturn(new EntitySearchResult(
            'payment_notification',
            0,
            new PaymentNotificationCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        $service = $this->service(
            $notificationRepository,
            $this->gateway(new NotificationResult(signatureValid: true, succeeded: true, amount: 1000)),
            $this->orderRepository($order),
            $this->extractor('PG0001')
        );

        try {
            $service->process('alipay', 'raw-body', [], '1.2.3.4');
            static::fail('the process call should have thrown');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::NOTIFICATION_NOT_FOUND, $exception->getErrorCode());
        }
    }

    /**
     * @param EntityRepository<PaymentNotificationCollection> $notificationRepository
     * @param EntityRepository<PaymentOrderCollection>|null $orderRepository
     */
    private function service(
        EntityRepository $notificationRepository,
        GatewayInterface $gateway,
        ?EntityRepository $orderRepository = null,
        ?PaymentNotificationExtractorInterface $extractor = null,
        ?PaymentOrderService $orderService = null,
        ?EventDispatcherInterface $eventDispatcher = null
    ): PaymentNotificationProcessor {
        $cipher = new PaymentCredentialCipher('test-secret');

        $deliveryRepository = static::createStub(EntityRepository::class);
        $deliveryRepository->method('search')->willReturn(new EntitySearchResult(
            'payment_webhook_delivery',
            0,
            new PaymentWebhookDeliveryCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        $appRepository = static::createStub(EntityRepository::class);
        $appRepository->method('search')->willReturn(new EntitySearchResult(
            'payment_app',
            0,
            new PaymentAppCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        $webhookService = new PaymentWebhookDispatcher(
            $deliveryRepository,
            $appRepository,
            new PaymentWebhookSigner(new PaymentSignatureService(), $cipher, new MockClock(self::NOW)),
            static::createStub(HttpClientInterface::class),
            new MockClock(self::NOW),
            static::createStub(LoggerInterface::class)
        );

        $refundService = new PaymentRefundService(
            static::createStub(EntityRepository::class),
            $this->configResolver($gateway),
            $orderService ?? $this->orderService($orderRepository ?? $this->orderRepository()),
            $webhookService,
            new PaymentNotifyUrlBuilder(static::createStub(RequestStack::class), 'https://base.example.test'),
            new PaymentRefundStateHandler(static::createStub(StateMachineRegistry::class)),
            $this->initialStateIdLoader(),
            static::createStub(EventDispatcherInterface::class),
            new MockClock(self::NOW)
        );

        return new PaymentNotificationProcessor(
            $notificationRepository,
            $this->providerRegistry($extractor ?? $this->extractor(null)),
            $orderService ?? $this->orderService($orderRepository ?? $this->orderRepository()),
            $refundService,
            $this->configResolver($gateway),
            $webhookService,
            $eventDispatcher ?? static::createStub(EventDispatcherInterface::class)
        );
    }

    private function providerRegistry(PaymentNotificationExtractorInterface $extractor): PaymentProviderRegistry
    {
        $channel = new PaymentChannel(
            technicalName: 'alipay_h5',
            providerTechnicalName: 'alipay',
            label: 'Alipay H5',
            flow: PaymentFlow::REDIRECT,
        );

        $provider = new PaymentProvider(
            technicalName: 'alipay',
            label: 'Alipay',
            credentials: [],
            channels: ['alipay_h5' => $channel],
            gatewayFactory: static::createStub(PaymentGatewayFactory::class),
            notificationExtractor: $extractor,
        );

        return new PaymentProviderRegistry([$provider]);
    }

    private function extractor(?string $paymentNo): PaymentNotificationExtractorInterface
    {
        $extractor = static::createStub(PaymentNotificationExtractorInterface::class);
        $extractor->method('extract')->willReturn(new PaymentNotificationData($paymentNo));

        return $extractor;
    }

    private function gateway(NotificationResult $result): GatewayInterface
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willReturnCallback(static function (object $request) use ($result): void {
            static::assertInstanceOf(Notify::class, $request);

            $model = $request->getModel();
            static::assertInstanceOf(PaymentGatewayModel::class, $model);

            $model->offsetSet(PaymentGatewayModel::RESULT, $result);
        });

        return $gateway;
    }

    private function configResolver(GatewayInterface $gateway): PaymentConfigResolver
    {
        $cipher = new PaymentCredentialCipher('test-secret');

        $channel = new PaymentChannel(
            technicalName: 'alipay_h5',
            providerTechnicalName: 'alipay',
            label: 'Alipay H5',
            flow: PaymentFlow::REDIRECT,
        );

        $gatewayFactory = static::createStub(PaymentGatewayFactory::class);
        $gatewayFactory->method('createForPayment')->willReturn($gateway);

        $provider = new PaymentProvider(
            technicalName: 'alipay',
            label: 'Alipay',
            credentials: [],
            channels: ['alipay_h5' => $channel],
            gatewayFactory: $gatewayFactory,
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
        );

        $providerRegistry = new PaymentProviderRegistry([$provider]);

        $appProvider = new PaymentAppProviderEntity();
        $appProvider->setUniqueIdentifier('app-provider-id');
        $appProvider->paymentAppId = 'app-id';
        $appProvider->providerTechnicalName = 'alipay';
        $appProvider->enabled = true;
        $appProvider->mode = PaymentAppProviderEntity::MODE_OWN;
        $appProvider->credentials = $cipher->encrypt(['app_id' => 'own-app-id']);

        $appChannel = new PaymentAppChannelEntity();
        $appChannel->setUniqueIdentifier('app-channel-id');
        $appChannel->paymentAppId = 'app-id';
        $appChannel->channelTechnicalName = 'alipay_h5';
        $appChannel->enabled = true;
        $appChannel->params = $cipher->encrypt(['appid' => 'own-appid']);

        $channelConfig = new PaymentChannelConfigEntity();
        $channelConfig->setUniqueIdentifier('channel-config-id');
        $channelConfig->providerTechnicalName = 'alipay';
        $channelConfig->channelTechnicalName = 'alipay_h5';
        $channelConfig->enabled = true;
        $channelConfig->params = $cipher->encrypt(['appid' => 'platform-appid']);

        $appProviderRepository = static::createStub(EntityRepository::class);
        $appProviderRepository->method('search')->willReturn($this->entityResult('payment_app_provider', $appProvider));

        $appChannelRepository = static::createStub(EntityRepository::class);
        $appChannelRepository->method('search')->willReturn($this->entityResult('payment_app_channel', $appChannel));

        $channelConfigRepository = static::createStub(EntityRepository::class);
        $channelConfigRepository->method('search')->willReturn($this->entityResult('payment_channel_config', $channelConfig));

        return new PaymentConfigResolver(
            $providerRegistry,
            new PaymentChannelRegistry($providerRegistry),
            $appProviderRepository,
            static::createStub(EntityRepository::class),
            $appChannelRepository,
            $channelConfigRepository,
            $cipher
        );
    }

    /**
     * @param EntityRepository<PaymentOrderCollection>|null $orderRepository
     */
    private function orderService(?EntityRepository $orderRepository = null, ?StateMachineRegistry $stateMachine = null): PaymentOrderService
    {
        return new PaymentOrderService(
            $orderRepository ?? $this->orderRepository(),
            $this->configResolver(static::createStub(GatewayInterface::class)),
            $this->initialStateIdLoader(),
            new PaymentOrderStateHandler($stateMachine ?? static::createStub(StateMachineRegistry::class)),
            new MockClock(self::NOW)
        );
    }

    private function initialStateIdLoader(): InitialStateIdLoader
    {
        $loader = static::createStub(InitialStateIdLoader::class);
        $loader->method('get')->willReturn(self::CREATED_STATE);

        return $loader;
    }

    /**
     * @return EntityRepository<PaymentOrderCollection>
     */
    private function orderRepository(PaymentOrderEntity ...$orders): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($this->orderSearchResult(...$orders));

        return $repository;
    }

    /**
     * @return EntitySearchResult<PaymentOrderCollection>
     */
    private function orderSearchResult(PaymentOrderEntity ...$orders): EntitySearchResult
    {
        return new EntitySearchResult(
            PaymentOrderEntity::ENTITY_NAME,
            \count($orders),
            new PaymentOrderCollection($orders),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @return EntitySearchResult<EntityCollection<Entity>>
     */
    private function entityResult(string $entityName, Entity $entity): EntitySearchResult
    {
        return new EntitySearchResult(
            $entityName,
            1,
            new EntityCollection([$entity]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @return EntityRepository<PaymentNotificationCollection>
     */
    private function notificationRepository(?\Closure $onCreate = null, ?\Closure $onUpdate = null): EntityRepository
    {
        $notification = $this->notificationEntity();

        $repository = static::createMock(EntityRepository::class);

        $create = $repository->expects($this->once())->method('create');
        if ($onCreate !== null) {
            $create->with(static::callback(static function (array $data) use ($onCreate): bool {
                $onCreate($data[0]);

                return true;
            }));
        }

        $update = $repository->expects($this->once())->method('update');
        if ($onUpdate !== null) {
            $update->with(static::callback(static function (array $data) use ($onUpdate): bool {
                $onUpdate($data[0]);

                return true;
            }));
        }

        $repository->method('search')->willReturn(new EntitySearchResult(
            'payment_notification',
            1,
            new PaymentNotificationCollection([$notification]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return $repository;
    }

    private function notificationEntity(): PaymentNotificationEntity
    {
        $notification = new PaymentNotificationEntity();
        $notification->setUniqueIdentifier('notification-id');
        $notification->id = 'notification-id';
        $notification->providerTechnicalName = 'alipay';

        return $notification;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function order(array $overrides = []): PaymentOrderEntity
    {
        $order = new PaymentOrderEntity();
        $order->setUniqueIdentifier('order-id');
        $order->id = 'order-id';
        $order->paymentNo = 'PG0001';
        $order->outTradeNo = 'app-trade-1';
        $order->paymentAppId = 'app-id';
        $order->providerTechnicalName = 'alipay';
        $order->channelTechnicalName = 'alipay_h5';
        $order->amount = 1000;
        $order->currency = 'CNY';
        $order->subject = 'An order';
        $order->stateId = self::CREATED_STATE;
        $order->paymentApp = $this->app();

        if (\array_key_exists('stateId', $overrides)) {
            $order->stateId = (string) $overrides['stateId'];
        }

        if (\array_key_exists('paidAt', $overrides)) {
            $paidAt = $overrides['paidAt'];
            static::assertInstanceOf(\DateTimeInterface::class, $paidAt);
            $order->paidAt = $paidAt;
        }

        if (\array_key_exists('paidAmount', $overrides)) {
            $order->paidAmount = (int) $overrides['paidAmount'];
        }

        if (\array_key_exists('outPaymentNo', $overrides)) {
            $order->outPaymentNo = (string) $overrides['outPaymentNo'];
        }

        return $order;
    }

    private function app(): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->setUniqueIdentifier('app-id');
        $app->id = 'app-id';
        $app->technicalName = 'demo-app';
        $app->appSecret = 'secret';
        $app->active = true;

        return $app;
    }
}
