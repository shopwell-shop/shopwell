<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Gateway\NotificationResult;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PayResult;
use Shopwell\Core\System\Payment\Gateway\RefundResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\PaymentRefundStates;
use Shopwell\Core\System\Payment\Struct\PayParams;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentGatewayModel::class)]
class PaymentGatewayModelTest extends TestCase
{
    public function testForOrderExposesTheOrderData(): void
    {
        $expireAt = new \DateTimeImmutable('2024-05-01 12:00:00');
        $order = $this->createOrder($expireAt);

        $model = PaymentGatewayModel::forOrder($order);

        static::assertSame('payment-no', $model->offsetGet(PaymentGatewayModel::PAYMENT_NO));
        static::assertSame('out-trade-no', $model->offsetGet(PaymentGatewayModel::OUT_TRADE_NO));
        static::assertSame(5000, $model->offsetGet(PaymentGatewayModel::AMOUNT));
        static::assertSame('CNY', $model->offsetGet(PaymentGatewayModel::CURRENCY));
        static::assertSame('subject', $model->offsetGet(PaymentGatewayModel::SUBJECT));
        static::assertSame('body', $model->offsetGet(PaymentGatewayModel::BODY));
        static::assertSame('attach', $model->offsetGet(PaymentGatewayModel::ATTACH));
        static::assertSame('127.0.0.1', $model->offsetGet(PaymentGatewayModel::CLIENT_IP));
        static::assertSame('web', $model->offsetGet(PaymentGatewayModel::DEVICE));
        static::assertSame($expireAt, $model->offsetGet(PaymentGatewayModel::EXPIRE_AT));
    }

    public function testForRefundCombinesTheOrderAndTheRefund(): void
    {
        $order = $this->createOrder();
        $order->outPaymentNo = 'out-payment-no';

        $refund = new PaymentRefundEntity();
        $refund->refundNo = 'refund-no';
        $refund->outRefundNo = 'out-refund-no';
        $refund->amount = 1000;
        $refund->currency = 'CNY';
        $refund->reason = 'customer request';

        $model = PaymentGatewayModel::forRefund($order, $refund);

        static::assertSame('payment-no', $model->offsetGet(PaymentGatewayModel::PAYMENT_NO));
        static::assertSame('out-trade-no', $model->offsetGet(PaymentGatewayModel::OUT_TRADE_NO));
        static::assertSame('out-payment-no', $model->offsetGet(PaymentGatewayModel::OUT_PAYMENT_NO));
        static::assertSame(1000, $model->offsetGet(PaymentGatewayModel::AMOUNT));
        static::assertSame(5000, $model->offsetGet(PaymentGatewayModel::ORDER_AMOUNT));
        static::assertSame('CNY', $model->offsetGet(PaymentGatewayModel::CURRENCY));
        static::assertSame('subject', $model->offsetGet(PaymentGatewayModel::SUBJECT));
        static::assertSame('refund-no', $model->offsetGet(PaymentGatewayModel::REFUND_NO));
        static::assertSame('out-refund-no', $model->offsetGet(PaymentGatewayModel::OUT_REFUND_NO));
        static::assertSame('customer request', $model->offsetGet(PaymentGatewayModel::REASON));
    }

    public function testForNotificationExposesTheRawRequest(): void
    {
        $headers = ['content-type' => 'application/json'];

        $model = PaymentGatewayModel::forNotification('{"ok":true}', $headers, '10.0.0.1');

        static::assertSame('{"ok":true}', $model->offsetGet(PaymentGatewayModel::RAW_BODY));
        static::assertSame($headers, $model->offsetGet(PaymentGatewayModel::HEADERS));
        static::assertSame('10.0.0.1', $model->offsetGet(PaymentGatewayModel::REMOTE_IP));
    }

    public function testForNotificationAllowsAnUnknownRemoteIp(): void
    {
        $model = PaymentGatewayModel::forNotification('{}', [], null);

        static::assertTrue($model->offsetExists(PaymentGatewayModel::REMOTE_IP));
        static::assertNull($model->offsetGet(PaymentGatewayModel::REMOTE_IP));
    }

    public function testRecordAndReadTheRequestAndTheResponse(): void
    {
        $model = new \ArrayObject();

        static::assertSame([], PaymentGatewayModel::requestOf($model));
        static::assertSame([], PaymentGatewayModel::responseOf($model));

        PaymentGatewayModel::recordRequest($model, ['out_trade_no' => 'x']);
        PaymentGatewayModel::recordResponse($model, ['trade_no' => 'y']);

        static::assertSame(['out_trade_no' => 'x'], PaymentGatewayModel::requestOf($model));
        static::assertSame(['trade_no' => 'y'], PaymentGatewayModel::responseOf($model));
    }

    public function testRequestOfIgnoresANonArrayPayload(): void
    {
        $model = new \ArrayObject([PaymentGatewayModel::REQUEST => 'not-an-array']);

        static::assertSame([], PaymentGatewayModel::requestOf($model));
    }

    public function testPayResultReturnsTheTypedResult(): void
    {
        $result = new PayResult(new PayParams(PaymentFlow::REDIRECT), 'trade-no');
        $model = new \ArrayObject([PaymentGatewayModel::RESULT => $result]);

        static::assertSame($result, PaymentGatewayModel::payResult($model, 'alipay'));
    }

    public function testPayResultThrowsWhenTheResultIsMissing(): void
    {
        $this->expectExceptionObject(PaymentException::gatewayResultMissing('alipay'));

        PaymentGatewayModel::payResult(new \ArrayObject(), 'alipay');
    }

    public function testPayResultThrowsWhenAnotherResultWasStored(): void
    {
        $model = new \ArrayObject([PaymentGatewayModel::RESULT => new RefundResult(PaymentRefundStates::STATE_FINISHED)]);

        $this->expectExceptionObject(PaymentException::gatewayResultMissing('alipay'));

        PaymentGatewayModel::payResult($model, 'alipay');
    }

    public function testRefundResultReturnsTheTypedResult(): void
    {
        $result = new RefundResult(PaymentRefundStates::STATE_FINISHED, 'refund-id');
        $model = new \ArrayObject([PaymentGatewayModel::RESULT => $result]);

        static::assertSame($result, PaymentGatewayModel::refundResult($model, 'alipay'));
    }

    public function testRefundResultThrowsWhenTheResultIsMissing(): void
    {
        $this->expectExceptionObject(PaymentException::gatewayResultMissing('alipay'));

        PaymentGatewayModel::refundResult(new \ArrayObject(), 'alipay');
    }

    public function testNotificationResultReturnsTheTypedResult(): void
    {
        $result = new NotificationResult(true, true);
        $model = new \ArrayObject([PaymentGatewayModel::RESULT => $result]);

        static::assertSame($result, PaymentGatewayModel::notificationResult($model, 'wechat'));
    }

    public function testNotificationResultThrowsWhenTheResultIsMissing(): void
    {
        $this->expectExceptionObject(PaymentException::gatewayResultMissing('wechat'));

        PaymentGatewayModel::notificationResult(new \ArrayObject(), 'wechat');
    }

    private function createOrder(?\DateTimeImmutable $expireAt = null): PaymentOrderEntity
    {
        $order = new PaymentOrderEntity();
        $order->paymentNo = 'payment-no';
        $order->outTradeNo = 'out-trade-no';
        $order->amount = 5000;
        $order->currency = 'CNY';
        $order->subject = 'subject';
        $order->body = 'body';
        $order->attach = 'attach';
        $order->clientIp = '127.0.0.1';
        $order->device = 'web';
        $order->expireAt = $expireAt;

        return $order;
    }
}
