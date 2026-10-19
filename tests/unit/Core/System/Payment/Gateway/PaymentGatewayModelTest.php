<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PayParams;
use Shopwell\Core\System\Payment\Gateway\NotificationResult;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PayResult;
use Shopwell\Core\System\Payment\Gateway\QueryResult;
use Shopwell\Core\System\Payment\Gateway\RefundResult;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundStates;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;

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

    public function testForQueryCombinesTheOrderDataAndTheLastChannelResponse(): void
    {
        $expireAt = new \DateTimeImmutable('2024-05-01 12:00:00');
        $order = $this->createOrder($expireAt);

        $model = PaymentGatewayModel::forQuery($order, ['id' => 'ORDER-1']);

        static::assertSame('payment-no', $model->offsetGet(PaymentGatewayModel::PAYMENT_NO));
        static::assertSame('out-trade-no', $model->offsetGet(PaymentGatewayModel::OUT_TRADE_NO));
        static::assertSame(5000, $model->offsetGet(PaymentGatewayModel::AMOUNT));
        static::assertSame('CNY', $model->offsetGet(PaymentGatewayModel::CURRENCY));
        static::assertSame($expireAt, $model->offsetGet(PaymentGatewayModel::EXPIRE_AT));
        static::assertSame(['id' => 'ORDER-1'], $model->offsetGet(PaymentGatewayModel::LAST_CHANNEL_RESPONSE));
    }

    public function testForQueryAllowsAnEmptyLastChannelResponse(): void
    {
        $model = PaymentGatewayModel::forQuery($this->createOrder());

        static::assertSame([], $model->offsetGet(PaymentGatewayModel::LAST_CHANNEL_RESPONSE));
    }

    public function testForNotificationAllowsAnUnknownRemoteIp(): void
    {
        $model = PaymentGatewayModel::forNotification('{}', [], null);

        static::assertTrue($model->offsetExists(PaymentGatewayModel::REMOTE_IP));
        static::assertNull($model->offsetGet(PaymentGatewayModel::REMOTE_IP));
    }

    public function testRecordAndReadTheRequestAndTheResponse(): void
    {
        $model = new PaymentGatewayModel();

        static::assertSame([], $model->request());
        static::assertSame([], $model->response());

        $model->recordRequest(['out_trade_no' => 'x']);
        $model->recordResponse(['trade_no' => 'y']);

        static::assertSame(['out_trade_no' => 'x'], $model->request());
        static::assertSame(['trade_no' => 'y'], $model->response());
    }

    public function testRequestIgnoresANonArrayPayload(): void
    {
        $model = new PaymentGatewayModel([PaymentGatewayModel::REQUEST => 'not-an-array']);

        static::assertSame([], $model->request());
    }

    public function testResultReturnsTheStoredResultOfTheRequestedType(): void
    {
        $payResult = new PayResult(new PayParams(PaymentFlow::REDIRECT), 'trade-no');
        $refundResult = new RefundResult(PaymentRefundStates::STATE_FINISHED, 'refund-id');
        $notificationResult = new NotificationResult(true, true);
        $queryResult = new QueryResult(QueryResult::STATUS_PAID, 'trade-no');

        $model = new PaymentGatewayModel();

        $model->offsetSet(PaymentGatewayModel::RESULT, $payResult);
        static::assertSame($payResult, $model->result(PayResult::class, 'alipay'));

        $model->offsetSet(PaymentGatewayModel::RESULT, $refundResult);
        static::assertSame($refundResult, $model->result(RefundResult::class, 'alipay'));

        $model->offsetSet(PaymentGatewayModel::RESULT, $notificationResult);
        static::assertSame($notificationResult, $model->result(NotificationResult::class, 'wechat'));

        $model->offsetSet(PaymentGatewayModel::RESULT, $queryResult);
        static::assertSame($queryResult, $model->result(QueryResult::class, 'stripe'));
    }

    public function testResultThrowsWhenTheResultIsMissing(): void
    {
        $this->expectExceptionObject(PaymentException::gatewayResultMissing('alipay'));

        (new PaymentGatewayModel())->result(PayResult::class, 'alipay');
    }

    public function testResultThrowsWhenAnotherResultWasStored(): void
    {
        $model = new PaymentGatewayModel([PaymentGatewayModel::RESULT => new RefundResult(PaymentRefundStates::STATE_FINISHED)]);

        $this->expectExceptionObject(PaymentException::gatewayResultMissing('alipay'));

        $model->result(PayResult::class, 'alipay');
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
