<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Alipay;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Notify;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayApi;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayGatewayFactory;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayNotifyAction;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AlipayNotifyAction::class)]
class AlipayNotifyActionTest extends TestCase
{
    public function testSupportsOnlyNotifyRequestsWithAnArrayObjectModel(): void
    {
        $action = new AlipayNotifyAction();

        static::assertTrue($action->supports(new Notify(new \ArrayObject())));
        static::assertFalse($action->supports(new Capture(new \ArrayObject())));
        static::assertFalse($action->supports(new Notify(new \stdClass())));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new AlipayNotifyAction();

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Capture(new \ArrayObject()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new AlipayNotifyAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(AlipayNotifyAction::class));

        $action->execute(new Notify(new \ArrayObject()));
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

        $result = PaymentGatewayModel::notificationResult($model, AlipayGatewayFactory::CHANNEL_H5);

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

        $result = PaymentGatewayModel::notificationResult($model, AlipayGatewayFactory::CHANNEL_H5);

        static::assertTrue($result->succeeded);
        static::assertSame('TRADE_FINISHED', $result->status);
    }

    public function testExecuteMarksATradeThatDidNotSucceedAsNotSucceeded(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=WAIT_BUYER_PAY');

        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, AlipayGatewayFactory::CHANNEL_H5);

        static::assertFalse($result->succeeded);
        static::assertSame('WAIT_BUYER_PAY', $result->status);
    }

    public function testExecuteUppercasesTheCurrencyAndConvertsTheAmount(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=TRADE_SUCCESS&total_amount=1.5&currency=usd');

        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, AlipayGatewayFactory::CHANNEL_H5);

        static::assertSame('USD', $result->currency);
        static::assertSame(150, $result->amount);
    }

    public function testExecuteDefaultsToTheCnyCurrency(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=TRADE_SUCCESS&total_amount=5');

        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, AlipayGatewayFactory::CHANNEL_H5);

        static::assertSame(AlipayNotifyAction::DEFAULT_CURRENCY, $result->currency);
        static::assertSame(500, $result->amount);
    }

    public function testExecuteIgnoresAnAmountThatIsNotANumber(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=TRADE_SUCCESS&total_amount=not-a-number');

        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, AlipayGatewayFactory::CHANNEL_H5);

        static::assertNull($result->amount);
    }

    public function testExecuteWithoutATradeNumberReportsNoOutPaymentNo(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=TRADE_SUCCESS');

        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, AlipayGatewayFactory::CHANNEL_H5);

        static::assertNull($result->outPaymentNo);
        static::assertNull($result->amount);
    }

    public function testExecuteMarksASignatureAsInvalidWhenThePublicKeyIsMissing(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('trade_status=TRADE_SUCCESS&sign=whatever&sign_type=RSA2');

        $action->execute(new Notify($model));

        $result = PaymentGatewayModel::notificationResult($model, AlipayGatewayFactory::CHANNEL_H5);

        static::assertFalse($result->signatureValid);
        static::assertTrue($result->succeeded);
    }

    public function testExecuteRecordsTheParsedBodyAsResponse(): void
    {
        $action = new AlipayNotifyAction();
        $action->setApi($this->api());

        $model = $this->model('out_trade_no=P-1&trade_status=TRADE_SUCCESS');

        $action->execute(new Notify($model));

        $response = PaymentGatewayModel::responseOf($model);
        static::assertSame('P-1', $response['out_trade_no']);
        static::assertSame('TRADE_SUCCESS', $response['trade_status']);
    }

    /**
     * @return \ArrayObject<string, mixed>
     */
    private function model(string $rawBody): \ArrayObject
    {
        return new \ArrayObject([
            PaymentGatewayModel::RAW_BODY => $rawBody,
        ]);
    }

    /**
     * @param array<string, mixed> $credentials
     */
    private function api(array $credentials = []): AlipayApi
    {
        return new AlipayApi([
            AbstractPaymentApi::CONFIG_PROVIDER => 'alipay',
            AbstractPaymentApi::CONFIG_CHANNEL => AlipayGatewayFactory::CHANNEL_H5,
            AbstractPaymentApi::CONFIG_MODE => 'own',
            AbstractPaymentApi::CONFIG_CREDENTIALS => $credentials,
            AbstractPaymentApi::CONFIG_PARAMS => [],
            AbstractPaymentApi::CONFIG_RUNTIME_PARAMS => [],
            AbstractPaymentApi::CONFIG_SETTINGS => [],
            AbstractPaymentApi::CONFIG_NOTIFY_URL => 'https://example.com/notify',
            AbstractPaymentApi::CONFIG_RETURN_URL => '',
        ]);
    }
}
