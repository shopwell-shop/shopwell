<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Alipay;

use Alipay\OpenAPISDK\Util\AlipaySignature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayApi;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayTransferNotificationVerifier;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AlipayTransferNotificationVerifier::class)]
class AlipayTransferNotificationVerifierTest extends TestCase
{
    /**
     * @var array<string, string>
     */
    private array $credentials;

    private string $privateKey;

    protected function setUp(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($key);

        $privateKey = '';
        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);
        static::assertIsArray($details);

        $this->privateKey = $privateKey;
        $this->credentials = [AlipayApi::PUBLIC_KEY => $details['key']];
    }

    public function testProviderIsAlipay(): void
    {
        static::assertSame(AlipayApi::PROVIDER, (new AlipayTransferNotificationVerifier())->provider());
    }

    public function testAMessageWithoutASignatureIsRejected(): void
    {
        static::assertFalse($this->verifier()->verify($this->credentials, 'out_biz_no=PO-1', []));
    }

    public function testAnEmptySignatureIsRejected(): void
    {
        static::assertFalse($this->verifier()->verify($this->credentials, 'out_biz_no=PO-1&sign=', []));
    }

    public function testAMessageSignedWithAnotherKeyIsRejected(): void
    {
        $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($other);

        $otherPrivateKey = '';
        openssl_pkey_export($other, $otherPrivateKey);

        $body = $this->signedBody(['out_biz_no' => 'PO-1'], $otherPrivateKey);

        static::assertFalse($this->verifier()->verify($this->credentials, $body, []));
    }

    public function testATamperedBodyIsRejected(): void
    {
        $body = $this->signedBody(['out_biz_no' => 'PO-1'], $this->privateKey);
        $body = str_replace('PO-1', 'PO-2', $body);

        static::assertFalse($this->verifier()->verify($this->credentials, $body, []));
    }

    public function testAMessageWithoutTheAlipayPublicKeyThrows(): void
    {
        $body = $this->signedBody(['out_biz_no' => 'PO-1'], $this->privateKey);

        $this->expectExceptionObject(PaymentException::missingCredentials(AlipayApi::PROVIDER, AlipayApi::PUBLIC_KEY));

        $this->verifier()->verify([], $body, []);
    }

    public function testASignedMessageIsAccepted(): void
    {
        $body = $this->signedBody(['out_biz_no' => 'PO-1', 'trade_status' => 'TRADE_SUCCESS'], $this->privateKey);

        static::assertTrue($this->verifier()->verify($this->credentials, $body, []));
    }

    public function testTheSignTypeOfTheMessageWins(): void
    {
        $body = $this->signedBody(['out_biz_no' => 'PO-1'], $this->privateKey, 'RSA2');

        static::assertTrue($this->verifier()->verify(
            $this->credentials + [AlipayApi::SIGN_TYPE => 'rsa'],
            $body,
            []
        ));
    }

    public function testThePayoutNumberIsTheOutBizNo(): void
    {
        static::assertSame('PO-1', $this->verifier()->outNoOf([], 'out_biz_no=PO-1&sign=x', []));
    }

    public function testThePayoutNumberIsNullWithoutAnOutBizNo(): void
    {
        static::assertNull($this->verifier()->outNoOf([], 'trade_status=TRADE_SUCCESS', []));
    }

    public function testAnEmptyOutBizNoIsNoPayoutNumber(): void
    {
        static::assertNull($this->verifier()->outNoOf([], 'out_biz_no=&sign=x', []));
    }

    public function testAlipayIsAcknowledgedWithThePlainWordSuccess(): void
    {
        $ack = $this->verifier()->acknowledge();

        static::assertSame('success', $ack->body);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function signedBody(array $parameters, string $privateKey, string $signType = 'RSA2'): string
    {
        $envelope = preg_replace('/-----[^-]+-----|\s/', '', $privateKey);
        static::assertIsString($envelope);

        $parameters['sign'] = AlipaySignature::rsaSign($parameters, $envelope, $signType);
        $parameters['sign_type'] = $signType;

        return http_build_query($parameters);
    }

    private function verifier(): AlipayTransferNotificationVerifier
    {
        return new AlipayTransferNotificationVerifier();
    }
}
