<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Gateway;

use Payum\Core\GatewayInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Gateway\CloseResult;
use Shopwell\Core\System\Payment\Gateway\PaymentClose;
use Shopwell\Core\System\Payment\Gateway\PaymentCloser;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentCloser::class)]
class PaymentCloserTest extends TestCase
{
    private const NOW = '2024-01-01T00:00:00+00:00';

    public function testItClosesTheTradeByTheMerchantOrderNumberAndKeepsTheAttempt(): void
    {
        $transactions = static::createMock(EntityRepository::class);
        $transactions->expects($this->once())
            ->method('create')
            ->with(static::callback(function (array $data): bool {
                static::assertSame('close', $data[0]['type']);
                static::assertSame(PaymentTransactionEntity::STATUS_PENDING, $data[0]['status']);
                static::assertSame('alipay', $data[0]['providerTechnicalName']);
                static::assertSame('alipay_h5', $data[0]['channelTechnicalName']);
                static::assertSame('order-id', $data[0]['paymentOrderId']);
                static::assertStringStartsWith('PT20240101000000', $data[0]['transactionNo']);

                return true;
            }));
        $transactions->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame(PaymentTransactionEntity::STATUS_SUCCESS, $data[0]['status']);
                static::assertSame(['out_trade_no' => 'app-trade-1'], $data[0]['requestPayload']);
                static::assertSame(['status' => CloseResult::STATUS_CLOSED], $data[0]['responsePayload']);
                static::assertNull($data[0]['errorCode']);
                static::assertNull($data[0]['errorMessage']);

                return true;
            }));

        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willReturnCallback(static function (object $request): void {
            static::assertInstanceOf(PaymentClose::class, $request);

            $model = $request->getModel();
            static::assertInstanceOf(PaymentGatewayModel::class, $model);
            // the trade is addressed by the number the merchant opened it with - the credentials of
            // the merchant account are enough, no matter which channel opened it
            static::assertSame('app-trade-1', $model->offsetGet(PaymentGatewayModel::OUT_TRADE_NO));

            $model->recordRequest(['out_trade_no' => 'app-trade-1']);
            $model->recordResponse(['status' => CloseResult::STATUS_CLOSED]);
            $model->offsetSet(PaymentGatewayModel::RESULT, new CloseResult(CloseResult::STATUS_CLOSED, 'TRADE_CLOSED'));
        });

        $result = $this->close($transactions, $gateway)->close($this->order());

        static::assertSame(CloseResult::STATUS_CLOSED, $result->status);
        static::assertSame('TRADE_CLOSED', $result->channelStatus);
    }

    public function testAFailingChannelIsKeptAsAFailedTransaction(): void
    {
        $transactions = static::createMock(EntityRepository::class);
        $transactions->expects($this->once())->method('create');
        $transactions->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame(PaymentTransactionEntity::STATUS_FAILED, $data[0]['status']);
                static::assertSame('boom', $data[0]['errorMessage']);
                static::assertSame('42', $data[0]['errorCode']);
                static::assertSame('boom', $data[0]['responsePayload']['exception']);

                return true;
            }));

        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willThrowException(new \RuntimeException('boom', 42));

        try {
            $this->close($transactions, $gateway)->close($this->order());
            static::fail('the gateway failure has to be reported');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
            // the stack and the raw answer of the channel stay reachable through the original exception
            static::assertInstanceOf(\RuntimeException::class, $exception->getPrevious());
            static::assertSame(42, $exception->getPrevious()->getCode());
        }
    }

    /**
     * The action already says what went wrong at the channel - a channel that rejects the close with
     * its own words must not be reworded by the service around it.
     */
    public function testAnErrorOfTheActionIsPassedOnUntouched(): void
    {
        $transactions = static::createMock(EntityRepository::class);
        $transactions->expects($this->once())->method('create');
        $transactions->expects($this->once())->method('update');

        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willThrowException(PaymentException::missingCredentials('alipay', 'app_id'));

        $this->expectExceptionObject(PaymentException::missingCredentials('alipay', 'app_id'));

        $this->close($transactions, $gateway)->close($this->order());
    }

    public function testAnAnswerWithoutResultIsAGatewayError(): void
    {
        $transactions = static::createMock(EntityRepository::class);
        $transactions->expects($this->once())->method('create');
        $transactions->expects($this->once())
            ->method('update')
            ->with(static::callback(static fn (array $data): bool => $data[0]['status'] === PaymentTransactionEntity::STATUS_FAILED));

        $gateway = static::createStub(GatewayInterface::class);

        $this->expectExceptionObject(PaymentException::gatewayResultMissing('alipay_h5'));

        $this->close($transactions, $gateway)->close($this->order());
    }

    public function testAnOrderWithoutItsAppIsNotFound(): void
    {
        $this->expectExceptionObject(PaymentException::orderNotFound('PG0001'));

        $this->close(static::createStub(EntityRepository::class), static::createStub(GatewayInterface::class))
            ->close($this->order(withApp: false));
    }

    /**
     * @param EntityRepository<PaymentTransactionCollection> $transactions
     */
    private function close(EntityRepository $transactions, GatewayInterface $gateway): PaymentCloser
    {
        return new PaymentCloser($transactions, $this->configResolver($gateway), new MockClock(self::NOW));
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

    private function order(bool $withApp = true): PaymentOrderEntity
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
        $order->stateId = 'state-opened';
        $order->returnUrl = 'https://return.example.test';

        if ($withApp) {
            $order->paymentApp = $this->app();
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
