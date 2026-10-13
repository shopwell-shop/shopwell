<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Service;

use Payum\Core\GatewayFactoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\PaymentOrderStates;
use Shopwell\Core\System\Payment\Provider\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Service\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Service\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Service\PaymentOrderService;
use Shopwell\Core\System\Payment\Struct\PaymentOrderRequest;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentOrderService::class)]
class PaymentOrderServiceTest extends TestCase
{
    private const CREATED_STATE = 'state-created';

    private const NOW = '2024-01-01T00:00:00+00:00';

    public function testIsPayableForAnOpenOrder(): void
    {
        static::assertTrue($this->service($this->orderRepository())->isPayable($this->order()));
    }

    public function testIsNotPayableWhenTheOrderWasPaid(): void
    {
        $order = $this->order(['paidAt' => new \DateTimeImmutable(self::NOW)]);

        static::assertFalse($this->service($this->orderRepository())->isPayable($order));
    }

    public function testIsNotPayableWhenTheOrderWasClosed(): void
    {
        $order = $this->order(['closedAt' => new \DateTimeImmutable(self::NOW)]);

        static::assertFalse($this->service($this->orderRepository())->isPayable($order));
    }

    public function testIsCreatedWhenTheOrderIsInTheInitialState(): void
    {
        static::assertTrue($this->service($this->orderRepository())->isCreated($this->order()));
    }

    public function testIsNotCreatedWhenTheOrderMovedOn(): void
    {
        $order = $this->order(['stateId' => 'state-opened']);

        static::assertFalse($this->service($this->orderRepository())->isCreated($order));
    }

    public function testGetByIdReturnsTheOrder(): void
    {
        $order = $this->order();

        $service = $this->service($this->orderRepository([$order]));

        static::assertSame($order, $service->getById('order-id'));
    }

    public function testGetByIdThrowsWhenTheOrderDoesNotExist(): void
    {
        $service = $this->service($this->orderRepository());

        $this->expectExceptionObject(PaymentException::orderNotFound('missing-id'));

        $service->getById('missing-id');
    }

    public function testFindByPaymentNoReturnsTheOrder(): void
    {
        $order = $this->order();

        static::assertSame($order, $this->service($this->orderRepository([$order]))->findByPaymentNo('PG0001'));
    }

    public function testFindByPaymentNoReturnsNullWhenTheOrderIsUnknown(): void
    {
        static::assertNull($this->service($this->orderRepository())->findByPaymentNo('PG0001'));
    }

    public function testGetByPaymentNoReturnsTheOrderOfTheApp(): void
    {
        $order = $this->order();
        $service = $this->service($this->orderRepository([$order]));

        static::assertSame($order, $service->getByPaymentNo('PG0001', $this->app('app-id')));
    }

    public function testGetByPaymentNoThrowsWhenTheOrderDoesNotExist(): void
    {
        $service = $this->service($this->orderRepository());

        $this->expectExceptionObject(PaymentException::orderNotFound('PG0001'));

        $service->getByPaymentNo('PG0001', $this->app('app-id'));
    }

    public function testGetByPaymentNoThrowsWhenTheOrderBelongsToAnotherApp(): void
    {
        $order = $this->order();
        $service = $this->service($this->orderRepository([$order]));

        $this->expectExceptionObject(PaymentException::foreignApp('other-app', 'PG0001'));

        $service->getByPaymentNo('PG0001', $this->app('other-app-id', 'other-app'));
    }

    public function testCloseTransitionsAndStampsTheClosedAt(): void
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame('order-id', $data[0]['id']);

                $closedAt = $data[0]['closedAt'];
                static::assertInstanceOf(\DateTimeInterface::class, $closedAt);
                static::assertSame(self::NOW, $closedAt->format(\DateTimeInterface::ATOM));

                return true;
            }));

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_CLOSE));

        $this->service($repository, null, null, $stateMachine)->close($this->order());
    }

    public function testCloseThrowsWhenTheOrderIsAlreadyClosed(): void
    {
        $order = $this->order(['closedAt' => new \DateTimeImmutable(self::NOW)]);

        $this->expectExceptionObject(PaymentException::orderAlreadyClosed('PG0001'));

        $this->service($this->orderRepository())->close($order);
    }

    public function testCloseThrowsWhenTheOrderIsAlreadyPaid(): void
    {
        $order = $this->order(['paidAt' => new \DateTimeImmutable(self::NOW)]);

        $this->expectExceptionObject(PaymentException::orderAlreadyClosed('PG0001'));

        $this->service($this->orderRepository())->close($order);
    }

    public function testMarkOpenedTransitionsARequestedOrder(): void
    {
        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_PROCESS));

        $this->service($this->orderRepository(), null, null, $stateMachine)->markOpened($this->order());
    }

    public function testMarkOpenedDoesNothingWhenTheOrderWasAlreadyOpened(): void
    {
        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->never())->method('transition');

        $order = $this->order(['stateId' => 'state-opened']);

        $this->service($this->orderRepository(), null, null, $stateMachine)->markOpened($order);
    }

    public function testMarkPaidTransitionsAndPersistsTheConfirmation(): void
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame(1000, $data[0]['paidAmount']);
                static::assertSame('channel-trade-1', $data[0]['outPaymentNo']);
                static::assertSame(['trade' => 'raw'], $data[0]['channelData']);

                $paidAt = $data[0]['paidAt'];
                static::assertInstanceOf(\DateTimeInterface::class, $paidAt);
                static::assertSame(self::NOW, $paidAt->format(\DateTimeInterface::ATOM));

                return true;
            }));

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_PAY));

        $this->service($repository, null, null, $stateMachine)
            ->markPaid($this->order(), 1000, 'channel-trade-1', ['trade' => 'raw']);
    }

    public function testMarkFailedTransitionsTheOrder(): void
    {
        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_FAIL));

        $this->service($this->orderRepository(), null, null, $stateMachine)->markFailed($this->order());
    }

    public function testApplyRefundKeepsAPartiallyRefundedOrderOpen(): void
    {
        $refunded = $this->order(['refundedAmount' => 300, 'paidAmount' => 1000, 'stateId' => 'state-refunded_partially']);

        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame(300, $data[0]['refundedAmount']);

                return true;
            }));
        $repository->method('search')->willReturn($this->searchResult([$refunded]));

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_REFUND_PARTIALLY));

        $order = $this->order(['paidAmount' => 1000]);
        $result = $this->service($repository, null, null, $stateMachine)->applyRefund($order, 300);

        static::assertSame($refunded, $result);
    }

    public function testApplyRefundClosesAFullyRefundedOrder(): void
    {
        $refunded = $this->order(['refundedAmount' => 1000, 'paidAmount' => 1000, 'stateId' => 'state-refunded']);

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($this->searchResult([$refunded]));

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_REFUND));

        $order = $this->order(['paidAmount' => 1000]);
        $result = $this->service($repository, null, null, $stateMachine)->applyRefund($order, 1000);

        static::assertSame(1000, $result->refundedAmount);
    }

    public function testExpireDueExpiresOnlyThePayableOrders(): void
    {
        $payable = $this->order();
        $alreadyPaid = $this->order(['id' => 'order-2', 'paidAt' => new \DateTimeImmutable(self::NOW)]);

        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->searchResult([$payable, $alreadyPaid]));
        $repository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame('order-id', $data[0]['id']);

                return true;
            }));

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_EXPIRE));

        static::assertSame(1, $this->service($repository, null, null, $stateMachine)->expireDue());
    }

    public function testExpireDueReturnsZeroWhenNothingIsDue(): void
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->searchResult([]));
        $repository->expects($this->never())->method('update');

        static::assertSame(0, $this->service($repository)->expireDue());
    }

    public function testCreateReturnsTheExistingOrderForAKnownOutTradeNo(): void
    {
        $existing = $this->order();

        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->searchResult([$existing]));
        $repository->expects($this->never())->method('create');

        $result = $this->service($repository, $this->workingConfigResolver())
            ->create($this->app('app-id'), $this->request());

        static::assertSame($existing, $result);
    }

    public function testCreatePersistsANewOrder(): void
    {
        $created = $this->order();

        $repository = static::createMock(EntityRepository::class);
        $repository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls($this->searchResult([]), $this->searchResult([$created]));
        $repository->expects($this->once())
            ->method('create')
            ->with(static::callback(function (array $data): bool {
                $row = $data[0];

                static::assertStringStartsWith('PG20240101000000', $row['paymentNo']);
                static::assertSame('app-trade-1', $row['outTradeNo']);
                static::assertSame('app-id', $row['paymentAppId']);
                static::assertSame('alipay', $row['providerTechnicalName']);
                static::assertSame('alipay_h5', $row['channelTechnicalName']);
                static::assertNull($row['channelParams']);
                static::assertSame(1000, $row['amount']);
                static::assertSame('CNY', $row['currency']);
                static::assertSame('An order', $row['subject']);
                static::assertSame(self::CREATED_STATE, $row['stateId']);
                static::assertSame('https://notify.example.test', $row['notifyUrl']);
                static::assertSame('https://return.example.test', $row['returnUrl']);

                $expireAt = $row['expireAt'];
                static::assertInstanceOf(\DateTimeInterface::class, $expireAt);
                static::assertSame('2024-01-01T00:30:00+00:00', $expireAt->format(\DateTimeInterface::ATOM));

                return true;
            }));

        $result = $this->service($repository, $this->workingConfigResolver())
            ->create($this->app('app-id'), $this->request());

        static::assertSame($created, $result);
    }

    public function testCreateHonoursTheRequestedExpiry(): void
    {
        $created = $this->order();

        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturnOnConsecutiveCalls($this->searchResult([]), $this->searchResult([$created]));
        $repository->expects($this->once())
            ->method('create')
            ->with(static::callback(function (array $data): bool {
                $expireAt = $data[0]['expireAt'];
                static::assertInstanceOf(\DateTimeInterface::class, $expireAt);
                static::assertSame('2024-01-01T00:10:00+00:00', $expireAt->format(\DateTimeInterface::ATOM));

                return true;
            }));

        $request = new PaymentOrderRequest(
            outTradeNo: 'app-trade-1',
            channel: 'alipay_h5',
            amount: 1000,
            currency: 'CNY',
            subject: 'An order',
            expireMinutes: 10,
        );

        $this->service($repository, $this->workingConfigResolver())->create($this->app('app-id'), $request);
    }

    /**
     * @param EntityRepository<PaymentOrderCollection> $repository
     */
    private function service(
        EntityRepository $repository,
        ?PaymentConfigResolver $configResolver = null,
        ?InitialStateIdLoader $initialStateIdLoader = null,
        ?StateMachineRegistry $stateMachineRegistry = null
    ): PaymentOrderService {
        return new PaymentOrderService(
            $repository,
            $configResolver ?? $this->inertConfigResolver(),
            $initialStateIdLoader ?? $this->initialStateIdLoader(),
            $stateMachineRegistry ?? static::createStub(StateMachineRegistry::class),
            new MockClock(self::NOW)
        );
    }

    private function initialStateIdLoader(): InitialStateIdLoader
    {
        $loader = static::createStub(InitialStateIdLoader::class);
        $loader->method('get')->willReturn(self::CREATED_STATE);

        return $loader;
    }

    private function inertConfigResolver(): PaymentConfigResolver
    {
        $providerRegistry = new PaymentProviderRegistry([]);

        return new PaymentConfigResolver(
            $providerRegistry,
            new PaymentChannelRegistry($providerRegistry),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            new PaymentCredentialCipher('test-secret')
        );
    }

    private function workingConfigResolver(): PaymentConfigResolver
    {
        $cipher = new PaymentCredentialCipher('test-secret');

        $channel = new PaymentChannel(
            technicalName: 'alipay_h5',
            providerName: 'alipay',
            label: 'Alipay H5',
            flow: PaymentFlow::REDIRECT,
        );

        $provider = new PaymentProvider(
            technicalName: 'alipay',
            label: 'Alipay',
            credentials: [],
            channels: ['alipay_h5' => $channel],
            gatewayFactory: static::createStub(GatewayFactoryInterface::class),
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
        $appProviderRepository->method('search')->willReturn($this->searchResult([$appProvider], 'payment_app_provider'));

        $appChannelRepository = static::createStub(EntityRepository::class);
        $appChannelRepository->method('search')->willReturn($this->searchResult([$appChannel], 'payment_app_channel'));

        $channelConfigRepository = static::createStub(EntityRepository::class);
        $channelConfigRepository->method('search')->willReturn($this->searchResult([$channelConfig], 'payment_channel_config'));

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
     * @param list<PaymentOrderEntity> $orders
     *
     * @return EntityRepository<PaymentOrderCollection>
     */
    private function orderRepository(array $orders = []): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn($this->searchResult($orders));

        return $repository;
    }

    /**
     * @param list<Entity> $entities
     *
     * @return EntitySearchResult<PaymentOrderCollection>
     */
    private function searchResult(array $entities, string $entityName = PaymentOrderEntity::ENTITY_NAME): EntitySearchResult
    {
        if ($entityName !== PaymentOrderEntity::ENTITY_NAME) {
            return new EntitySearchResult(
                $entityName,
                \count($entities),
                new EntityCollection($entities),
                null,
                new Criteria(),
                Context::createDefaultContext()
            );
        }

        /** @var list<PaymentOrderEntity> $entities */
        return new EntitySearchResult(
            $entityName,
            \count($entities),
            new PaymentOrderCollection($entities),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function order(array $overrides = []): PaymentOrderEntity
    {
        $order = new PaymentOrderEntity();
        $order->setUniqueIdentifier((string) ($overrides['id'] ?? 'order-id'));
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

        if (\array_key_exists('id', $overrides)) {
            $order->id = (string) $overrides['id'];
        }

        if (\array_key_exists('stateId', $overrides)) {
            $order->stateId = (string) $overrides['stateId'];
        }

        if (\array_key_exists('paidAt', $overrides)) {
            $paidAt = $overrides['paidAt'];
            static::assertInstanceOf(\DateTimeInterface::class, $paidAt);
            $order->paidAt = $paidAt;
        }

        if (\array_key_exists('closedAt', $overrides)) {
            $closedAt = $overrides['closedAt'];
            static::assertInstanceOf(\DateTimeInterface::class, $closedAt);
            $order->closedAt = $closedAt;
        }

        if (\array_key_exists('paidAmount', $overrides)) {
            $order->paidAmount = (int) $overrides['paidAmount'];
        }

        if (\array_key_exists('refundedAmount', $overrides)) {
            $order->refundedAmount = (int) $overrides['refundedAmount'];
        }

        return $order;
    }

    private function app(string $id, string $technicalName = 'demo-app'): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->setUniqueIdentifier($id);
        $app->id = $id;
        $app->technicalName = $technicalName;
        $app->appSecret = 'secret';
        $app->active = true;

        return $app;
    }

    private function request(): PaymentOrderRequest
    {
        return new PaymentOrderRequest(
            outTradeNo: 'app-trade-1',
            channel: 'alipay_h5',
            amount: 1000,
            currency: 'CNY',
            subject: 'An order',
            notifyUrl: 'https://notify.example.test',
            returnUrl: 'https://return.example.test',
        );
    }
}
