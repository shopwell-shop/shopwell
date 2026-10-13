<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Service\PaymentSignatureService;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentSignatureService::class)]
class PaymentSignatureServiceTest extends TestCase
{
    public function testAuthParametersAreTheFourTopLevelKeys(): void
    {
        static::assertSame(['app_id', 'timestamp', 'nonce', 'sign'], PaymentSignatureService::authParameters());
    }

    public function testSignBuildsTheDocumentedCanonicalContent(): void
    {
        $service = new PaymentSignatureService();

        $params = [
            PaymentSignatureService::APP_ID => 'app-1',
            PaymentSignatureService::TIMESTAMP => 1700000000,
            PaymentSignatureService::NONCE => 'n-1',
            PaymentSignatureService::SIGN => 'ignored',
            'order_no' => 'P-1',
        ];

        $expected = base64_encode(hash_hmac(
            'sha256',
            "app-1\n1700000000\nn-1\nPOST\n/payment-api/v1/orders\n{\"order_no\":\"P-1\"}",
            'secret',
            true
        ));

        static::assertSame(
            $expected,
            $service->sign('secret', 'app-1', 1700000000, 'n-1', 'post', '/payment-api/v1/orders', $params)
        );
    }

    public function testSignIsStableForTheSameInput(): void
    {
        $service = new PaymentSignatureService();
        $params = ['order_no' => 'P-1'];

        static::assertSame(
            $service->sign('secret', 'app-1', 1700000000, 'n-1', 'POST', '/path', $params),
            $service->sign('secret', 'app-1', 1700000000, 'n-1', 'POST', '/path', $params)
        );
    }

    public function testVerifyAcceptsTheMatchingSignature(): void
    {
        $service = new PaymentSignatureService();
        $params = [
            PaymentSignatureService::APP_ID => 'app-1',
            PaymentSignatureService::TIMESTAMP => 1700000000,
            PaymentSignatureService::NONCE => 'n-1',
            'order_no' => 'P-1',
        ];

        $sign = $service->sign('secret', 'app-1', 1700000000, 'n-1', 'POST', '/path', $params);

        static::assertTrue($service->verify('secret', 'app-1', 1700000000, 'n-1', 'POST', '/path', $params, $sign));
    }

    public function testVerifyRejectsADifferentSecret(): void
    {
        $service = new PaymentSignatureService();
        $params = ['order_no' => 'P-1'];

        $sign = $service->sign('secret', 'app-1', 1700000000, 'n-1', 'POST', '/path', $params);

        static::assertFalse($service->verify('other-secret', 'app-1', 1700000000, 'n-1', 'POST', '/path', $params, $sign));
    }

    public function testVerifyRejectsAChangedBusinessParameter(): void
    {
        $service = new PaymentSignatureService();

        $sign = $service->sign('secret', 'app-1', 1700000000, 'n-1', 'POST', '/path', ['order_no' => 'P-1']);

        static::assertFalse(
            $service->verify('secret', 'app-1', 1700000000, 'n-1', 'POST', '/path', ['order_no' => 'P-2'], $sign)
        );
    }

    public function testVerifyRejectsAChangedNonceMethodOrPath(): void
    {
        $service = new PaymentSignatureService();
        $params = ['order_no' => 'P-1'];

        $sign = $service->sign('secret', 'app-1', 1700000000, 'n-1', 'POST', '/path', $params);

        static::assertFalse($service->verify('secret', 'app-1', 1700000000, 'n-2', 'POST', '/path', $params, $sign));
        static::assertFalse($service->verify('secret', 'app-1', 1700000000, 'n-1', 'GET', '/path', $params, $sign));
        static::assertFalse($service->verify('secret', 'app-1', 1700000000, 'n-1', 'POST', '/other', $params, $sign));
        static::assertFalse($service->verify('secret', 'app-1', 1700000001, 'n-1', 'POST', '/path', $params, $sign));
    }

    public function testCanonicalizeStripsTheAuthParametersAndSortsTheKeys(): void
    {
        $service = new PaymentSignatureService();

        $canonicalized = $service->canonicalize([
            'b' => 2,
            'a' => 1,
            PaymentSignatureService::APP_ID => 'app-1',
            PaymentSignatureService::TIMESTAMP => 1700000000,
            PaymentSignatureService::NONCE => 'n-1',
            PaymentSignatureService::SIGN => 'sign',
        ]);

        static::assertSame('{"a":1,"b":2}', $canonicalized);
    }

    public function testCanonicalizeKeepsUnicodeAndSlashesUnescaped(): void
    {
        $service = new PaymentSignatureService();

        static::assertSame('{"name":"张三/李四"}', $service->canonicalize(['name' => '张三/李四']));
    }

    public function testSignPayloadAndVerifyPayload(): void
    {
        $service = new PaymentSignatureService();

        $signature = $service->signPayload('secret', '{"payment_no":"P-1"}');

        static::assertSame(
            base64_encode(hash_hmac('sha256', '{"payment_no":"P-1"}', 'secret', true)),
            $signature
        );
        static::assertTrue($service->verifyPayload('secret', '{"payment_no":"P-1"}', $signature));
        static::assertFalse($service->verifyPayload('secret', '{"payment_no":"P-2"}', $signature));
        static::assertFalse($service->verifyPayload('other', '{"payment_no":"P-1"}', $signature));
    }
}
