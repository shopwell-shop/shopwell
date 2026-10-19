<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Wechat;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatNotificationExtractor;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WechatNotificationExtractor::class)]
class WechatNotificationExtractorTest extends TestCase
{
    public function testExtractReadsNothingFromTheEncryptedBody(): void
    {
        $extractor = new WechatNotificationExtractor();

        $data = $extractor->extract('{"resource":{"ciphertext":"encrypted"}}', ['wechatpay-serial' => 'SERIAL']);

        static::assertSame([null, null, null, null], [
            $data->paymentNo,
            $data->outTradeNo,
            $data->outPaymentNo,
            $data->refundNo,
        ]);
    }

    public function testAcknowledgeAnswersTheExpectedJsonDocument(): void
    {
        $ack = (new WechatNotificationExtractor())->acknowledge();

        static::assertSame('{"code":"SUCCESS","message":"成功"}', $ack->body);
        static::assertSame('application/json; charset=utf-8', $ack->contentType);
    }
}
