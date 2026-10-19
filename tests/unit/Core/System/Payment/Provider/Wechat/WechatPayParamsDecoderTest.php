<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Wechat;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Api\Struct\PayParams;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatCaptureAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatPayParamsDecoder;

/**
 * The answers below are the ones {@see WechatCaptureAction} records, so a change of the recorded shape
 * without the decoder fails here.
 *
 * Wechat has no self submitting form product: the browser products answer a url (h5) or a code url
 * (native), everything else is signed per call and only an app can consume it.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(WechatPayParamsDecoder::class)]
class WechatPayParamsDecoderTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, PayParams}>
     */
    public static function recordedAnswerProvider(): iterable
    {
        yield 'redirect to the url of the h5 product' => [
            [
                WechatCaptureAction::RESPONSE_H5_URL => 'https://wx.tenpay.com/cgi-bin/mmpayweb-bin/checkmweb?prepay_id=wx1',
                'prepay_id' => 'wx1',
            ],
            new PayParams(
                flow: PaymentFlow::REDIRECT,
                payUrl: 'https://wx.tenpay.com/cgi-bin/mmpayweb-bin/checkmweb?prepay_id=wx1',
                httpMethod: 'GET'
            ),
        ];

        yield 'qr code of the native product' => [
            [
                WechatCaptureAction::RESPONSE_CODE_URL => 'weixin://wxpay/bizpayurl?pr=abc',
                'prepay_id' => 'wx2',
            ],
            new PayParams(flow: PaymentFlow::QRCODE, extra: ['qr_code' => 'weixin://wxpay/bizpayurl?pr=abc']),
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    #[DataProvider('recordedAnswerProvider')]
    public function testDecodesTheRecordedAnswer(array $response, PayParams $expected): void
    {
        static::assertEquals($expected, (new WechatPayParamsDecoder())->payParamsFromResponse($response));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unusableAnswerProvider(): iterable
    {
        yield 'jsapi product answers a prepay id that has to be signed per call' => [['prepay_id' => 'wx3']];
        yield 'app product answers a prepay id that has to be signed per call' => [['prepay_id' => 'wx4']];
        yield 'failed capture' => [['error' => 'the interface timed out']];
        yield 'nothing recorded' => [[]];
    }

    /**
     * @param array<string, mixed> $response
     */
    #[DataProvider('unusableAnswerProvider')]
    public function testReturnsNullForAnswersABrowserCannotContinue(array $response): void
    {
        static::assertNull((new WechatPayParamsDecoder())->payParamsFromResponse($response));
    }
}
