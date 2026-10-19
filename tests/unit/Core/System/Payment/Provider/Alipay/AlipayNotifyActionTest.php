<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Alipay;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Notify;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\NotificationResult;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayApi;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayNotifyAction;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AlipayNotifyAction::class)]
class AlipayNotifyActionTest extends TestCase
{
    public function testSupportsOnlyNotifyRequestsWithAPaymentGatewayModel(): void
    {
        $action = new AlipayNotifyAction();

        static::assertTrue($action->supports(new Notify(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Capture(new PaymentGatewayModel())));
        static::assertFalse($action->supports(new Notify(new \stdClass())));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new AlipayNotifyAction();

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Capture(new PaymentGatewayModel()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new AlipayNotifyAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(AlipayNotifyAction::class));

        $action->execute(new Notify(new PaymentGatewayModel()));
    }

    public function testSetApiRejectsAnApiItDoesNotWorkWith(): void
    {
        $action = new AlipayNotifyAction();

        $this->expectExceptionObject(PaymentException::unsupportedApi(AlipayNotifyAction::class, \stdClass::class));

        $action->setApi(new \stdClass());
    }

    public function testExecuteReadsASucceededTrade(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('out_trade_no=P-1&trade_no=2024010100001&trade_status=TRADE_SUCCESS&total_amount=12.34&currency=cny');

        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, AlipayApi::CHANNEL_H5);

        static::assertFalse($result->signatureValid);
        static::assertTrue($result->succeeded);
        static::assertSame('2024010100001', $result->outPaymentNo);
        static::assertSame(1234, $result->amount);
        static::assertSame('CNY', $result->currency);
        static::assertSame('TRADE_SUCCESS', $result->status);
        static::assertSame('2024010100001', $result->raw['trade_no']);
    }

    public function testExecuteTreatsAFinishedTradeAsSucceeded(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=TRADE_FINISHED');

        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, AlipayApi::CHANNEL_H5);

        static::assertTrue($result->succeeded);
        static::assertSame('TRADE_FINISHED', $result->status);
    }

    public function testExecuteMarksATradeThatDidNotSucceedAsNotSucceeded(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=WAIT_BUYER_PAY');

        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, AlipayApi::CHANNEL_H5);

        static::assertFalse($result->succeeded);
        static::assertSame('WAIT_BUYER_PAY', $result->status);
    }

    public function testExecuteUppercasesTheCurrencyAndConvertsTheAmount(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=TRADE_SUCCESS&total_amount=1.5&currency=usd');

        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, AlipayApi::CHANNEL_H5);

        static::assertSame('USD', $result->currency);
        static::assertSame(150, $result->amount);
    }

    public function testExecuteDefaultsToTheCnyCurrency(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=TRADE_SUCCESS&total_amount=5');

        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, AlipayApi::CHANNEL_H5);

        static::assertSame(AlipayNotifyAction::DEFAULT_CURRENCY, $result->currency);
        static::assertSame(500, $result->amount);
    }

    public function testExecuteIgnoresAnAmountThatIsNotANumber(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=TRADE_SUCCESS&total_amount=not-a-number');

        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, AlipayApi::CHANNEL_H5);

        static::assertNull($result->amount);
    }

    public function testExecuteWithoutATradeNumberReportsNoOutPaymentNo(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=TRADE_SUCCESS');

        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, AlipayApi::CHANNEL_H5);

        static::assertNull($result->outPaymentNo);
        static::assertNull($result->amount);
    }

    public function testExecuteMarksASignatureAsInvalidWhenThePublicKeyIsMissing(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=TRADE_SUCCESS&sign=whatever&sign_type=RSA2');

        $action->execute(new Notify($model));

        $result = $model->result(NotificationResult::class, AlipayApi::CHANNEL_H5);

        static::assertFalse($result->signatureValid);
        static::assertTrue($result->succeeded);
    }

    public function testExecuteRecordsTheParsedBodyAsResponse(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('out_trade_no=P-1&trade_status=TRADE_SUCCESS');

        $action->execute(new Notify($model));

        $response = $model->response();
        static::assertSame('P-1', $response['out_trade_no']);
        static::assertSame('TRADE_SUCCESS', $response['trade_status']);
    }

    private function model(string $rawBody): PaymentGatewayModel
    {
        return new PaymentGatewayModel([
            PaymentGatewayModel::RAW_BODY => $rawBody,
        ]);
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function api(array $credentials = []): AlipayApi
    {
        $channel = new PaymentChannel(AlipayApi::CHANNEL_H5, 'alipay', AlipayApi::CHANNEL_H5, PaymentFlow::REDIRECT);

        return new AlipayApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'alipay',
                    'alipay',
                    [],
                    [AlipayApi::CHANNEL_H5 => $channel],
                    new PaymentGatewayFactory('alipay', AlipayApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                channel: $channel,
                credentials: $credentials,
                params: [],
                runtimeParams: [],
                settings: [],
            ),
            'https://example.com/notify',
            '',
        );
    }
}
