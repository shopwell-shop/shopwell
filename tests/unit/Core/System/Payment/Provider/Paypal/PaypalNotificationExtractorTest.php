<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Paypal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalNotificationExtractor;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaypalNotificationExtractor::class)]
class PaypalNotificationExtractorTest extends TestCase
{
    public function testExtractReturnsEmptyDataForABodyThatIsNotJson(): void
    {
        $data = (new PaypalNotificationExtractor())->extract('not-json', []);

        static::assertSame([null, null, null, null], [
            $data->paymentNo,
            $data->outTradeNo,
            $data->outPaymentNo,
            $data->refundNo,
        ]);
    }

    public function testExtractReadsACaptureNotification(): void
    {
        $body = json_encode([
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'CAP-1', 'custom_id' => 'ORDER-1', 'invoice_id' => 'TRADE-1'],
        ], \JSON_THROW_ON_ERROR);

        $data = (new PaypalNotificationExtractor())->extract($body, []);

        static::assertSame('ORDER-1', $data->paymentNo);
        static::assertSame('TRADE-1', $data->outTradeNo);
        static::assertSame('CAP-1', $data->outPaymentNo);
        static::assertNull($data->refundNo);
    }

    public function testExtractReadsARefundNotification(): void
    {
        $body = json_encode([
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => ['id' => 'CAP-1', 'custom_id' => 'REFUND-1', 'invoice_id' => 'TRADE-1'],
        ], \JSON_THROW_ON_ERROR);

        $data = (new PaypalNotificationExtractor())->extract($body, []);

        // the custom id of a refund is the refund number, the payment number is deliberately empty
        static::assertNull($data->paymentNo);
        static::assertSame('TRADE-1', $data->outTradeNo);
        static::assertSame('CAP-1', $data->outPaymentNo);
        static::assertSame('REFUND-1', $data->refundNo);
    }

    public function testExtractIgnoresReferencesThatAreMissingOrEmpty(): void
    {
        $body = json_encode([
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 123, 'custom_id' => '', 'invoice_id' => null],
        ], \JSON_THROW_ON_ERROR);

        $data = (new PaypalNotificationExtractor())->extract($body, []);

        static::assertNull($data->paymentNo);
        static::assertNull($data->outTradeNo);
        static::assertSame('123', $data->outPaymentNo);
    }

    public function testExtractAcceptsABodyWithoutAnyResource(): void
    {
        $data = (new PaypalNotificationExtractor())->extract('{"event_type":"PAYMENT.CAPTURE.COMPLETED"}', []);

        static::assertSame([null, null, null, null], [
            $data->paymentNo,
            $data->outTradeNo,
            $data->outPaymentNo,
            $data->refundNo,
        ]);
    }

    public function testAcknowledgeAnswersAnEmptyJsonBody(): void
    {
        $ack = (new PaypalNotificationExtractor())->acknowledge();

        static::assertSame('', $ack->body);
        static::assertSame('application/json', $ack->contentType);
    }
}
