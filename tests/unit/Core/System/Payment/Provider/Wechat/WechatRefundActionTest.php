<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Wechat;

use Payum\Core\Request\Capture;
use Payum\Core\Request\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatApi;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatRefundAction;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WechatRefundAction::class)]
class WechatRefundActionTest extends TestCase
{
    public function testSupportsOnlyRefundRequestsWithAnArrayObjectModel(): void
    {
        $action = new WechatRefundAction();

        static::assertTrue($action->supports(new Refund(new \ArrayObject())));
        static::assertFalse($action->supports(new Capture(new \ArrayObject())));
        static::assertFalse($action->supports(new Refund(new \stdClass())));
    }

    public function testExecuteThrowsWithoutAnApi(): void
    {
        $action = new WechatRefundAction();

        $this->expectExceptionObject(PaymentException::actionWithoutApi(WechatRefundAction::class));

        $action->execute(new Refund(new \ArrayObject()));
    }

    public function testExecuteWrapsAGatewayFailureAndRecordsTheRefundRequest(): void
    {
        $model = new \ArrayObject([
            PaymentGatewayModel::PAYMENT_NO => 'ORDER-1',
            PaymentGatewayModel::AMOUNT => 25,
            PaymentGatewayModel::ORDER_AMOUNT => 100,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::REFUND_NO => 'REFUND-1',
            PaymentGatewayModel::REASON => 'customer asked',
        ]);

        $action = new WechatRefundAction();
        // the merchant certificate is incomplete, so the sdk fails before it would talk to the channel
        $action->setApi($this->api());

        try {
            $action->execute(new Refund($model));
            static::fail('The refund was expected to fail without a complete merchant certificate.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $request = PaymentGatewayModel::requestOf($model);
        static::assertSame('ORDER-1', $request['out_trade_no']);
        static::assertSame('REFUND-1', $request['out_refund_no']);
        static::assertSame('customer asked', $request['reason']);
        static::assertSame('https://example.com/notify', $request['notify_url']);
        static::assertSame(['refund' => 25, 'total' => 100, 'currency' => 'CNY'], $request['amount']);

        static::assertArrayHasKey('error', PaymentGatewayModel::responseOf($model));
    }

    public function testExecuteLeavesTheReasonOutWhenItIsMissing(): void
    {
        $model = new \ArrayObject([
            PaymentGatewayModel::PAYMENT_NO => 'ORDER-1',
            PaymentGatewayModel::AMOUNT => 25,
            PaymentGatewayModel::ORDER_AMOUNT => 100,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::REFUND_NO => 'REFUND-1',
        ]);

        $action = new WechatRefundAction();
        $action->setApi($this->api());

        try {
            $action->execute(new Refund($model));
            static::fail('The refund was expected to fail without a complete merchant certificate.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        static::assertArrayNotHasKey('reason', PaymentGatewayModel::requestOf($model));
    }

    #[DataProvider('refundUris')]
    public function testTheRefundUriIsStable(string $expected, string $actual): void
    {
        static::assertSame($expected, $actual);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function refundUris(): \Generator
    {
        yield 'refund' => ['v3/refund/domestic/refunds', WechatRefundAction::URI_REFUND];
    }

    private function api(): WechatApi
    {
        return new WechatApi([
            AbstractPaymentApi::CONFIG_PROVIDER => 'wechat',
            AbstractPaymentApi::CONFIG_CHANNEL => 'wechat_h5',
            AbstractPaymentApi::CONFIG_MODE => 'live',
            AbstractPaymentApi::CONFIG_CREDENTIALS => [WechatApi::MCH_ID => '1900000001'],
            AbstractPaymentApi::CONFIG_PARAMS => [],
            AbstractPaymentApi::CONFIG_SETTINGS => [],
            AbstractPaymentApi::CONFIG_RUNTIME_PARAMS => [],
            AbstractPaymentApi::CONFIG_NOTIFY_URL => 'https://example.com/notify',
            AbstractPaymentApi::CONFIG_RETURN_URL => '',
        ]);
    }
}
