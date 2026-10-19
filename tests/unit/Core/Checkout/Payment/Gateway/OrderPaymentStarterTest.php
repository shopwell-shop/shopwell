<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Gateway;

use Payum\Core\GatewayInterface;
use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Payment\Gateway\OrderPaymentStarter;
use Shopwell\Core\Checkout\Payment\Storefront\PlatformPaymentApp;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PayParams;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PayResult;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderPaymentStarter::class)]
class OrderPaymentStarterTest extends TestCase
{
    /**
     * @return iterable<string, array{PayParams, string}>
     */
    public static function flowProvider(): iterable
    {
        yield 'redirect to the channel url' => [
            new PayParams(PaymentFlow::REDIRECT, payUrl: 'https://pay.example.test', httpMethod: 'GET'),
            'https://pay.example.test',
        ];

        yield 'redirect to the self submitting form page' => [
            new PayParams(PaymentFlow::REDIRECT, httpMethod: 'POST', htmlForm: '<form></form>'),
            '/generated/frontend.payment.gateway.form/PG-1',
        ];

        yield 'qr code to the qr page' => [
            new PayParams(PaymentFlow::QRCODE, extra: ['qr_code' => 'https://qr.example.test']),
            '/generated/frontend.payment.gateway.qr/PG-1',
        ];
    }

    #[DataProvider('flowProvider')]
    public function testHandOverMapsTheFlowToAUrl(PayParams $params, string $expected): void
    {
        $url = $this->starter()->handOver(PaymentGatewayFixture::paymentOrder(), new PayResult($params));

        static::assertSame($expected, $url);
    }

    public function testHandOverRefusesAnSdkChannel(): void
    {
        $this->expectExceptionObject(PaymentException::channelNotAvailableInStorefront(PaymentGatewayFixture::CHANNEL));

        $this->starter()->handOver(
            PaymentGatewayFixture::paymentOrder(),
            new PayResult(new PayParams(PaymentFlow::SDK, extra: ['order_str' => 'signed']))
        );
    }

    public function testHandOverThrowsWhenTheChannelGaveNeitherUrlNorForm(): void
    {
        $this->expectExceptionObject(PaymentException::gatewayResultMissing(PaymentGatewayFixture::CHANNEL));

        $this->starter()->handOver(
            PaymentGatewayFixture::paymentOrder(),
            new PayResult(new PayParams(PaymentFlow::REDIRECT))
        );
    }

    public function testStartReusesTheOpenPaymentOrderWithoutCapturingAgain(): void
    {
        $paymentOrder = PaymentGatewayFixture::paymentOrder([
            'transactions' => [PaymentGatewayFixture::captureTransaction([
                'http_method' => 'GET',
                'answer' => 'https://pay.example.test',
            ])],
        ]);

        $paymentOrderRepository = $this->createMock(EntityRepository::class);
        $paymentOrderRepository->expects($this->once())
            ->method('search')
            ->willReturn(PaymentGatewayFixture::searchResult([$paymentOrder]));
        // the order is only reused: nothing is created at the platform and nothing at the channel
        $paymentOrderRepository->expects($this->never())->method('create');

        $transactionRepository = $this->createMock(EntityRepository::class);
        $transactionRepository->expects($this->never())->method('create');
        $transactionRepository->expects($this->never())->method('update');

        $gateway = $this->createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');

        $started = PaymentGatewayFixture::starter($paymentOrderRepository, $transactionRepository, $gateway)
            ->start($this->order(), $this->orderTransaction(), PaymentGatewayFixture::channel(), $this->app(), 'https://shop.example.test/return');

        static::assertSame($paymentOrder, $started['order']);
        static::assertSame(PaymentFlow::REDIRECT, $started['result']->params->flow);
        static::assertSame('https://pay.example.test', $started['result']->params->payUrl);
    }

    public function testStartCreatesThePaymentOrderForTheTransactionAndCapturesIt(): void
    {
        $created = PaymentGatewayFixture::paymentOrder();

        $paymentOrderRepository = $this->createMock(EntityRepository::class);
        $paymentOrderRepository->expects($this->exactly(3))
            ->method('search')
            ->willReturnCallback(static function (Criteria $criteria) use ($created): EntitySearchResult {
                // the lookup by id is the one that reads the order back after it was written
                if ($criteria->getIds() !== []) {
                    return PaymentGatewayFixture::searchResult([$created]);
                }

                return PaymentGatewayFixture::searchResult([]);
            });
        $paymentOrderRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(static function (array $data): bool {
                static::assertSame('order-transaction-id', $data[0]['outTradeNo']);
                static::assertSame(1999, $data[0]['amount']);
                static::assertSame('EUR', $data[0]['currency']);
                static::assertSame('SW-1000', $data[0]['subject']);
                static::assertSame('order-id', $data[0]['orderId']);
                static::assertSame('order-transaction-id', $data[0]['orderTransactionId']);

                return true;
            }));

        $transactionRepository = $this->createMock(EntityRepository::class);
        $transactionRepository->expects($this->once())->method('create');
        $transactionRepository->expects($this->once())->method('update');

        $payResult = new PayResult(new PayParams(PaymentFlow::REDIRECT, payUrl: 'https://pay.example.test'));
        $gateway = $this->gateway($payResult);

        $started = PaymentGatewayFixture::starter($paymentOrderRepository, $transactionRepository, $gateway)
            ->start($this->order(), $this->orderTransaction(), PaymentGatewayFixture::channel(), $this->app(), 'https://shop.example.test/return');

        static::assertSame($created, $started['order']);
        static::assertSame($payResult, $started['result']);
    }

    public function testStartCapturesAgainWhenTheExistingOrderHasNoChannelAnswer(): void
    {
        $existing = PaymentGatewayFixture::paymentOrder(['transactions' => []]);

        $paymentOrderRepository = $this->createMock(EntityRepository::class);
        // the latest payment order and the idempotent lookup by out trade number: the create hands the
        // existing order back, so it is not read again by id
        $paymentOrderRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(static function (Criteria $criteria) use ($existing): EntitySearchResult {
                return PaymentGatewayFixture::searchResult([$existing]);
            });
        // the create is idempotent and hands the existing order back, a second order is not written
        $paymentOrderRepository->expects($this->never())->method('create');

        $transactionRepository = $this->createMock(EntityRepository::class);
        $transactionRepository->expects($this->once())->method('create');
        $transactionRepository->expects($this->once())->method('update');

        $payResult = new PayResult(new PayParams(PaymentFlow::REDIRECT, payUrl: 'https://pay.example.test'));

        $started = PaymentGatewayFixture::starter(
            $paymentOrderRepository,
            $transactionRepository,
            $this->gateway($payResult)
        )->start($this->order(), $this->orderTransaction(), PaymentGatewayFixture::channel(), $this->app(), 'https://shop.example.test/return');

        static::assertSame($existing, $started['order']);
        static::assertSame($payResult, $started['result']);
    }

    /**
     * The tenant of the platform still authorizes itself on the first payment: the shop's own checkout
     * needs no onboarding by hand, and the migration only seeds the channel it starts with.
     */
    public function testStartAuthorizesTheInterfaceAndTheChannelForThePlatformTenant(): void
    {
        $paymentOrder = PaymentGatewayFixture::paymentOrder();
        $paymentOrderRepository = $this->paymentOrdersForCreate($paymentOrder);

        $transactionRepository = $this->createMock(EntityRepository::class);
        $transactionRepository->expects($this->once())->method('create');
        $transactionRepository->expects($this->once())->method('update');

        $appProviderRepository = $this->createMock(EntityRepository::class);
        $appProviderRepository->method('search')->willReturn(PaymentGatewayFixture::searchResult([]));
        $appProviderRepository->expects($this->once())
            ->method('upsert')
            ->with(static::callback(static function (array $payload): bool {
                static::assertSame(PaymentGatewayFixture::PLATFORM_APP_ID, $payload[0]['paymentAppId']);
                static::assertSame(PaymentGatewayFixture::PROVIDER, $payload[0]['providerTechnicalName']);
                // the tenant of the platform is the root of the chain: it inherits from nobody
                static::assertNull($payload[0]['sourceAppId']);
                static::assertTrue($payload[0]['enabled']);

                return true;
            }))
            ->willReturn(static::createStub(EntityWrittenContainerEvent::class));

        $appChannelRepository = $this->createMock(EntityRepository::class);
        $appChannelRepository->method('search')->willReturn(PaymentGatewayFixture::searchResult([]));
        $appChannelRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(static function (array $payload): bool {
                static::assertSame(PaymentGatewayFixture::PLATFORM_APP_ID, $payload[0]['paymentAppId']);
                static::assertSame(PaymentGatewayFixture::CHANNEL, $payload[0]['channelTechnicalName']);
                static::assertTrue($payload[0]['enabled']);

                return true;
            }))
            ->willReturn(static::createStub(EntityWrittenContainerEvent::class));

        $starter = new OrderPaymentStarter(
            new PlatformPaymentApp(
                PaymentGatewayFixture::repository([PaymentGatewayFixture::platformAppEntity()]),
                $appProviderRepository,
                $appChannelRepository,
                PaymentGatewayFixture::repository([PaymentGatewayFixture::providerConfig()]),
                PaymentGatewayFixture::repository([PaymentGatewayFixture::channelConfig()]),
                PaymentGatewayFixture::channelRegistry(),
                PaymentGatewayFixture::providerRegistry(),
            ),
            PaymentGatewayFixture::providerRegistry(),
            PaymentGatewayFixture::paymentOrderService($paymentOrderRepository),
            PaymentGatewayFixture::captureService(
                $transactionRepository,
                PaymentGatewayFixture::configResolver($this->gateway(
                    new PayResult(new PayParams(PaymentFlow::REDIRECT, payUrl: 'https://pay.example.test'))
                )),
                PaymentGatewayFixture::paymentOrderService($paymentOrderRepository)
            ),
            PaymentGatewayFixture::router()
        );

        $started = $starter->start(
            $this->order(),
            $this->orderTransaction(),
            PaymentGatewayFixture::channel(),
            $this->app(),
            'https://shop.example.test/return'
        );

        static::assertSame($paymentOrder, $started['order']);
    }

    /**
     * Another app brings its own authorizations: writing them from the checkout would overrule the
     * merchant's decision, so a channel the app does not offer is refused instead.
     */
    public function testStartRefusesAnAppThatDoesNotOfferTheChannel(): void
    {
        $app = PaymentGatewayFixture::externalApp();

        $starter = new OrderPaymentStarter(
            new PlatformPaymentApp(
                PaymentGatewayFixture::repository([$app]),
                PaymentGatewayFixture::repository(),
                PaymentGatewayFixture::repository(),
                PaymentGatewayFixture::repository(),
                PaymentGatewayFixture::repository(),
                PaymentGatewayFixture::channelRegistry(),
                PaymentGatewayFixture::providerRegistry(),
            ),
            PaymentGatewayFixture::providerRegistry(),
            PaymentGatewayFixture::paymentOrderService(PaymentGatewayFixture::repository()),
            PaymentGatewayFixture::captureService(
                PaymentGatewayFixture::repository(),
                PaymentGatewayFixture::configResolver(),
                PaymentGatewayFixture::paymentOrderService(PaymentGatewayFixture::repository())
            ),
            PaymentGatewayFixture::router()
        );

        $this->expectExceptionObject(PaymentException::channelNotEnabled(
            PaymentGatewayFixture::CHANNEL,
            $app->id
        ));

        $starter->start(
            $this->order(),
            $this->orderTransaction(),
            PaymentGatewayFixture::channel(),
            $app,
            'https://shop.example.test/return'
        );
    }

    private function starter(): OrderPaymentStarter
    {
        return PaymentGatewayFixture::starter(
            PaymentGatewayFixture::repository(),
            PaymentGatewayFixture::repository()
        );
    }

    /**
     * The account the shop's own checkout collects on.
     */
    private function app(): PaymentAppEntity
    {
        return PaymentGatewayFixture::platformAppEntity();
    }

    /**
     * @return EntityRepository<PaymentOrderCollection>
     */
    private function paymentOrdersForCreate(PaymentOrderEntity $created): EntityRepository
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturnCallback(
            static function (Criteria $criteria) use ($created): EntitySearchResult {
                return $criteria->getIds() === []
                    ? PaymentGatewayFixture::searchResult([])
                    : PaymentGatewayFixture::searchResult([$created]);
            }
        );
        $repository->method('create')->willReturn(static::createStub(EntityWrittenContainerEvent::class));
        $repository->method('update')->willReturn(static::createStub(EntityWrittenContainerEvent::class));

        return $repository;
    }

    private function gateway(PayResult $payResult): GatewayInterface
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willReturnCallback(static function (object $request) use ($payResult): void {
            \assert($request instanceof Capture);
            $model = $request->getModel();
            \assert($model instanceof PaymentGatewayModel);

            $model->offsetSet(PaymentGatewayModel::REQUEST, ['out_trade_no' => 'order-transaction-id']);
            $model->offsetSet(PaymentGatewayModel::RESPONSE, ['answer' => 'https://pay.example.test']);
            $model->offsetSet(PaymentGatewayModel::RESULT, $payResult);
        });

        return $gateway;
    }

    private function order(): OrderEntity
    {
        return PaymentGatewayFixture::order();
    }

    private function orderTransaction(): OrderTransactionEntity
    {
        return PaymentGatewayFixture::orderTransaction($this->order());
    }
}
