<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Gateway;

use Payum\Core\GatewayInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Exception\PaymentGatewayException;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Gateway\PaymentChannelCloser;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Request\CloseResult;
use Shopwell\Core\System\Payment\Gateway\Request\PaymentClose;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptEntity;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentTransaction\PaymentTransactionEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Tests\Unit\Core\System\Payment\NumberRangeFixture;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentChannelCloser::class)]
class PaymentChannelCloserTest extends TestCase
{
    /**
     * What alipay declares: the close is one of its request capabilities.
     */
    private const ALIPAY_CAPABILITIES = [PaymentCapability::CAPTURE, PaymentCapability::CLOSE];

    /**
     * What stripe and paypal declare: they have no close action at all.
     */
    private const WITHOUT_CLOSE_CAPABILITIES = [PaymentCapability::CAPTURE, PaymentCapability::REFUND];

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
                static::assertSame('PX1001', $data[0]['transactionNo']);

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
        $gateway->method('execute')->willThrowException(PaymentGatewayException::missingCredentials('alipay', 'app_id'));

        $this->expectExceptionObject(PaymentGatewayException::missingCredentials('alipay', 'app_id'));

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

        $this->expectExceptionObject(PaymentGatewayException::gatewayResultMissing('alipay_h5'));

        $this->close($transactions, $gateway)->close($this->order());
    }

    public function testAnOrderWithoutItsAppIsNotFound(): void
    {
        $this->expectExceptionObject(PaymentOrderException::orderNotFound('PG0001'));

        $this->close(static::createStub(EntityRepository::class), static::createStub(GatewayInterface::class))
            ->close($this->order(withApp: false));
    }

    /**
     * The declaration answers the question before a request is signed: an interface that never declared
     * a close is refused, and payum never gets the chance to fail inside the gateway - which the caller
     * would read as a broken channel (502) even though nothing was sent.
     */
    public function testAnInterfaceWithoutCloseIsRefusedBeforeAnythingIsSent(): void
    {
        // the attempt must not be booked: a call that cannot reach the channel has no transaction
        $transactions = static::createMock(EntityRepository::class);
        $transactions->expects($this->never())->method('create');
        $transactions->expects($this->never())->method('update');

        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');

        $this->expectExceptionObject(PaymentProviderException::providerLacksCapability('alipay', PaymentCapability::CLOSE->value));

        $this->close($transactions, $gateway, self::WITHOUT_CLOSE_CAPABILITIES)->close($this->order());
    }

    /**
     * The refusal says what the caller can act on and is not dressed up as a channel fault: a 400 with
     * its own code, and a message that names the missing capability instead of a broken channel.
     */
    public function testTheRefusalIsA400AndDoesNotReadLikeAChannelFault(): void
    {
        $transactions = static::createStub(EntityRepository::class);
        $gateway = static::createStub(GatewayInterface::class);

        try {
            $this->close($transactions, $gateway, self::WITHOUT_CLOSE_CAPABILITIES)->close($this->order());
            static::fail('the missing capability has to be reported');
        } catch (PaymentException $exception) {
            static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
            static::assertSame(PaymentException::PROVIDER_LACKS_CAPABILITY, $exception->getErrorCode());
            static::assertNotSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
            static::assertStringContainsString(PaymentCapability::CLOSE->value, $exception->getMessage());
            static::assertStringNotContainsString('returned an error', $exception->getMessage());
        }
    }

    public function testProductWithoutCloseIsRefusedBeforeBookingOrCallingTheProvider(): void
    {
        $transactions = static::createMock(EntityRepository::class);
        $transactions->expects($this->never())->method('create');
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');
        $this->expectExceptionObject(PaymentProviderException::providerLacksCapability('alipay', PaymentCapability::CLOSE->value));

        $this->close($transactions, $gateway, productCapabilities: [PaymentCapability::CAPTURE])->close($this->order());
    }

    /**
     * @param EntityRepository<PaymentTransactionCollection> $transactions
     * @param list<PaymentCapability> $capabilities
     * @param list<PaymentCapability>|null $productCapabilities
     */
    private function close(EntityRepository $transactions, GatewayInterface $gateway, array $capabilities = self::ALIPAY_CAPABILITIES, ?array $productCapabilities = null): PaymentChannelCloser
    {
        return new PaymentChannelCloser($transactions, $this->configResolver($gateway, $capabilities, $productCapabilities), NumberRangeFixture::generator('PX1001'));
    }

    /**
     * @param list<PaymentCapability> $capabilities
     * @param list<PaymentCapability>|null $productCapabilities
     */
    private function configResolver(GatewayInterface $gateway, array $capabilities = self::ALIPAY_CAPABILITIES, ?array $productCapabilities = null): PaymentConfigResolver
    {
        $channel = new PaymentProduct(
            technicalName: 'alipay_h5',
            providerTechnicalName: 'alipay',
            method: 'alipay',
            label: 'Alipay H5',
            interactions: [PaymentInteraction::REDIRECT],
            capabilities: $productCapabilities ?? $capabilities,
        );

        $gatewayFactory = static::createStub(PaymentGatewayFactory::class);
        $gatewayFactory->method('createForPayment')->willReturn($gateway);

        $provider = new PaymentProvider(
            technicalName: 'alipay',
            label: 'Alipay',
            credentials: [],
            products: ['alipay_h5' => $channel],
            gatewayFactory: $gatewayFactory,
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
            capabilities: $capabilities,
        );

        $resolved = new ResolvedPaymentConfig($provider, $channel, [], [], [], [], 'account-id');
        $resolver = static::createStub(PaymentConfigResolver::class);
        $resolver->method('resolveForOrder')->willReturn($resolved);

        return $resolver;
    }

    private function order(bool $withApp = true): PaymentOrderEntity
    {
        $order = new PaymentOrderEntity();
        $order->setUniqueIdentifier('order-id');
        $order->id = 'order-id';
        $order->paymentNo = 'PG0001';
        $order->outTradeNo = 'app-trade-1';
        $order->paymentAppId = 'app-id';
        $order->activeAttempt = new PaymentAttemptEntity();
        $order->activeAttempt->id = 'attempt-id';
        $order->activeAttempt->attemptNo = 'p-attempt';
        $order->activeAttempt->paymentOrderId = $order->id;
        $order->activeAttempt->providerTechnicalName = 'alipay';
        $order->activeAttemptId = 'attempt-id';
        $order->activeAttempt->channelTechnicalName = 'alipay_h5';
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
        $app->type = PaymentAppEntity::TYPE_EXTERNAL;
        $app->appSecret = 'secret';
        $app->active = true;

        return $app;
    }
}
