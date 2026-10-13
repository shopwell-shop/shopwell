<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Alipay;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Payum\Core\Request\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayApi;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayGatewayFactory;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayRefundAction;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AlipayRefundAction::class)]
class AlipayRefundActionTest extends TestCase
{
    public function testSupportsOnlyRefundRequestsWithAnArrayObjectModel(): void
    {
        $action = new AlipayRefundAction();

        static::assertTrue($action->supports(new Refund(new \ArrayObject())));
        static::assertFalse($action->supports(new Capture(new \ArrayObject())));
        static::assertFalse($action->supports(new Refund(new \stdClass())));
    }

    public function testExecuteRejectsARequestItDoesNotSupport(): void
    {
        $action = new AlipayRefundAction();

        $this->expectException(RequestNotSupportedException::class);

        $action->execute(new Capture(new \ArrayObject()));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new AlipayRefundAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(AlipayRefundAction::class));

        $action->execute(new Refund(new \ArrayObject()));
    }

    public function testSetApiRejectsAnApiItDoesNotWorkWith(): void
    {
        $action = new AlipayRefundAction();

        $this->expectExceptionObject(PaymentException::unsupportedApi(AlipayRefundAction::class, \stdClass::class));

        $action->setApi(new \stdClass());
    }

    public function testExecuteWrapsAGatewayFailureAndRecordsTheRefundRequest(): void
    {
        $action = new AlipayRefundAction();
        // without the app id the sdk fails while it is built, before it would talk to the channel
        $action->setApi($this->api());

        $model = new \ArrayObject([
            PaymentGatewayModel::PAYMENT_NO => 'P-1',
            PaymentGatewayModel::AMOUNT => 1234,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::REFUND_NO => 'R-1',
            PaymentGatewayModel::REASON => 'broken',
        ]);

        try {
            $action->execute(new Refund($model));
            static::fail('The refund was expected to fail without credentials.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $request = PaymentGatewayModel::requestOf($model);
        static::assertSame('P-1', $request['out_trade_no']);
        static::assertSame('R-1', $request['out_request_no']);
        static::assertSame('12.34', $request['refund_amount']);

        $response = PaymentGatewayModel::responseOf($model);
        static::assertArrayHasKey('error', $response);
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
