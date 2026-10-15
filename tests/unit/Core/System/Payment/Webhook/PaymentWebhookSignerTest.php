<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Webhook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookSigner;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentWebhookSigner::class)]
class PaymentWebhookSignerTest extends TestCase
{
    private const NOW = '2024-01-01T00:00:00+00:00';

    public function testSignBuildsThePayloadOfTheEventAndSignsItWithTheAppSecret(): void
    {
        $signer = $this->signer();

        $signed = $signer->sign($this->order(), $this->app(), PaymentWebhookDispatcher::EVENT_ORDER_PAID, ['foo' => 'bar']);

        static::assertSame([
            'event' => PaymentWebhookDispatcher::EVENT_ORDER_PAID,
            'payment_no' => 'PG0001',
            'out_trade_no' => 'app-trade-1',
            'attach' => 'attach-data',
            'data' => ['foo' => 'bar'],
            'timestamp' => 1704067200,
        ], $signed['payload']);

        // the app verifies the delivery with the very same HMAC the api requests are signed with
        $expected = (new PaymentSignatureService())->signPayload('app-secret', $signer->encode($signed['payload']));
        static::assertSame($expected, $signed['signature']);
    }

    public function testEncodeLeavesUnicodeAndSlashesUnescaped(): void
    {
        static::assertSame('{"subject":"中文订单 / 已支付"}', $this->signer()->encode(['subject' => '中文订单 / 已支付']));
    }

    private function signer(): PaymentWebhookSigner
    {
        return new PaymentWebhookSigner(
            new PaymentSignatureService(),
            new PaymentCredentialCipher('test-secret'),
            new MockClock(self::NOW)
        );
    }

    private function order(): PaymentOrderEntity
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
        $order->attach = 'attach-data';
        $order->stateId = 'state-opened';

        return $order;
    }

    private function app(): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->setUniqueIdentifier('app-id');
        $app->id = 'app-id';
        $app->technicalName = 'demo-app';
        $app->appSecret = (new PaymentCredentialCipher('test-secret'))->encryptValue('app-secret');
        $app->active = true;

        return $app;
    }
}
