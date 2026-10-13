<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Alipay;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayNotificationExtractor;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AlipayNotificationExtractor::class)]
class AlipayNotificationExtractorTest extends TestCase
{
    public function testExtractReadsTheReferencesOfAFormEncodedBody(): void
    {
        $data = (new AlipayNotificationExtractor())->extract(
            'out_trade_no=P-1&trade_no=2024010100001&trade_status=TRADE_SUCCESS',
            ['content-type' => ['application/x-www-form-urlencoded']]
        );

        static::assertSame('P-1', $data->paymentNo);
        static::assertSame('2024010100001', $data->outPaymentNo);
        static::assertNull($data->outTradeNo);
        static::assertNull($data->refundNo);
    }

    public function testExtractReturnsNullForMissingReferences(): void
    {
        $data = (new AlipayNotificationExtractor())->extract('trade_status=TRADE_SUCCESS', []);

        static::assertNull($data->paymentNo);
        static::assertNull($data->outPaymentNo);
    }

    public function testExtractTreatsAnEmptyValueAsMissing(): void
    {
        $data = (new AlipayNotificationExtractor())->extract('out_trade_no=&trade_no=', []);

        static::assertNull($data->paymentNo);
        static::assertNull($data->outPaymentNo);
    }

    public function testExtractIgnoresAnEmptyBody(): void
    {
        $data = (new AlipayNotificationExtractor())->extract('', []);

        static::assertNull($data->paymentNo);
        static::assertNull($data->outPaymentNo);
    }

    public function testExtractIgnoresANonScalarReference(): void
    {
        $data = (new AlipayNotificationExtractor())->extract('out_trade_no[]=P-1&out_trade_no[]=P-2', []);

        static::assertNull($data->paymentNo);
    }

    public function testAcknowledgeAnswersThePlainWordSuccess(): void
    {
        $ack = (new AlipayNotificationExtractor())->acknowledge();

        static::assertSame('success', $ack->body);
        static::assertSame('text/plain; charset=utf-8', $ack->contentType);
    }
}
