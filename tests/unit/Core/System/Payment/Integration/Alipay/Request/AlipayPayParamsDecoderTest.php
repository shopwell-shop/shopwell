<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Integration\Alipay\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentHandover;
use Shopwell\Core\System\Payment\Integration\Alipay\Action\AlipayCaptureAction;
use Shopwell\Core\System\Payment\Integration\Alipay\Request\AlipayPayParamsDecoder;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;

/**
 * The answers below are the ones {@see AlipayCaptureAction} records, so a change of the recorded shape
 * without the decoder fails here.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(AlipayPayParamsDecoder::class)]
class AlipayPayParamsDecoderTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, PaymentHandover}>
     */
    public static function recordedAnswerProvider(): iterable
    {
        yield 'redirect to the url of the channel' => [
            [
                AlipayCaptureAction::RESPONSE_HTTP_METHOD => 'GET',
                AlipayCaptureAction::RESPONSE_ANSWER => 'https://pay.example.test',
            ],
            new PaymentHandover(flow: PaymentInteraction::REDIRECT, payUrl: 'https://pay.example.test', httpMethod: 'GET'),
        ];

        yield 'self submitting form of a POST request' => [
            [
                AlipayCaptureAction::RESPONSE_HTTP_METHOD => 'POST',
                AlipayCaptureAction::RESPONSE_ANSWER => '<form action="https://pay.example.test"></form>',
            ],
            new PaymentHandover(flow: PaymentInteraction::REDIRECT, httpMethod: 'POST', htmlForm: '<form action="https://pay.example.test"></form>'),
        ];

        yield 'qr code of the precreate product' => [
            [AlipayCaptureAction::RESPONSE_QR_CODE => 'https://qr.alipay.com/abc'],
            new PaymentHandover(flow: PaymentInteraction::QRCODE, extra: ['qr_code' => 'https://qr.alipay.com/abc']),
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    #[DataProvider('recordedAnswerProvider')]
    public function testDecodesTheRecordedAnswer(array $response, PaymentHandover $expected): void
    {
        static::assertEquals($expected, (new AlipayPayParamsDecoder())->payParamsFromResponse($response));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unusableAnswerProvider(): iterable
    {
        yield 'app product answers an order string' => [['order_str' => 'app_id=2021000000000000&biz_content=%7B%7D']];
        yield 'mini program product answers a trade number' => [['trade_no' => '2026010122001']];
        yield 'failed capture' => [['error' => 'the interface timed out']];
        yield 'nothing recorded' => [[]];
    }

    /**
     * @param array<string, mixed> $response
     */
    #[DataProvider('unusableAnswerProvider')]
    public function testReturnsNullForAnswersABrowserCannotContinue(array $response): void
    {
        static::assertNull((new AlipayPayParamsDecoder())->payParamsFromResponse($response));
    }
}
