<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Wechat;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatApi;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatNotifyAction;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatTransferNotificationVerifier;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;

/**
 * Wechat signs `timestamp\nnonce\nbody\n` with the platform key and encrypts the payout inside the
 * resource. Both have to work before a notification is a trigger at all, and the payout number can
 * only be read after the resource was decrypted - which is why the decryption path is tested with a
 * resource this test encrypted itself.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(WechatTransferNotificationVerifier::class)]
class WechatTransferNotificationVerifierTest extends TestCase
{
    private const API_V3_KEY = 'abcdefghijklmnopqrstuvwxyz012345';

    private const SERIAL = 'PLATFORM-SERIAL';

    private const NONCE = 'abcdefghijkl';

    private string $privateKey;

    private string $publicKey;

    protected function setUp(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($key);

        $privateKey = '';
        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);
        static::assertIsArray($details);

        $this->privateKey = $privateKey;
        $this->publicKey = $details['key'];
    }

    public function testProviderIsWechat(): void
    {
        static::assertSame(WechatApi::PROVIDER, $this->verifier()->provider());
    }

    public function testANotificationWithoutTheSignatureHeadersIsRejected(): void
    {
        static::assertFalse($this->verifier()->verify($this->credentials(), '{}', []));
    }

    public function testANotificationMissingOnlyTheNonceIsRejected(): void
    {
        $headers = $this->headers('1700000000', self::NONCE, 'signature');
        unset($headers[WechatNotifyAction::HEADER_NONCE]);

        static::assertFalse($this->verifier()->verify($this->credentials(), '{}', $headers));
    }

    public function testASignatureOfAnotherKeyIsRejected(): void
    {
        $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($other);
        $otherPrivateKey = '';
        openssl_pkey_export($other, $otherPrivateKey);

        static::assertFalse($this->verifier()->verify(
            $this->credentials(),
            '{"id":"evt-1"}',
            $this->headers('1700000000', self::NONCE, $this->sign('{"id":"evt-1"}', '1700000000', self::NONCE, $otherPrivateKey))
        ));
    }

    public function testATamperedBodyIsRejected(): void
    {
        $headers = $this->headers('1700000000', self::NONCE, $this->sign('{"id":"evt-1"}', '1700000000', self::NONCE));

        static::assertFalse($this->verifier()->verify($this->credentials(), '{"id":"evt-2"}', $headers));
    }

    public function testAnUnknownSerialIsRejectedWhenSeveralCertificatesAreConfigured(): void
    {
        $credentials = [
            WechatApi::API_V3_KEY => self::API_V3_KEY,
            WechatApi::PLATFORM_CERTS => json_encode([
                self::SERIAL => $this->publicKey,
                'ANOTHER-SERIAL' => $this->publicKey,
            ], \JSON_THROW_ON_ERROR),
        ];

        $headers = $this->headers('1700000000', self::NONCE, $this->sign('{}', '1700000000', self::NONCE), 'UNKNOWN-SERIAL');

        static::assertFalse($this->verifier()->verify($credentials, '{}', $headers));
    }

    public function testASingleCertificateIsUsedForAnUnknownSerial(): void
    {
        $headers = $this->headers('1700000000', self::NONCE, $this->sign('{}', '1700000000', self::NONCE), 'ROTATED-SERIAL');

        static::assertTrue($this->verifier()->verify($this->credentials(), '{}', $headers));
    }

    public function testASignedNotificationIsAccepted(): void
    {
        $body = '{"id":"evt-1","event_type":"MCHTRANSFER.BILL.FINISHED"}';

        static::assertTrue($this->verifier()->verify(
            $this->credentials(),
            $body,
            $this->headers('1700000000', self::NONCE, $this->sign($body, '1700000000', self::NONCE))
        ));
    }

    public function testThePayoutNumberIsReadFromTheDecryptedResource(): void
    {
        $body = $this->encryptedBody(['out_bill_no' => 'PO-1', 'state' => 'SUCCESS']);

        static::assertSame('PO-1', $this->verifier()->outNoOf($this->credentials(), $body, []));
    }

    public function testADecryptedResourceWithoutAPayoutNumberIsNoPayoutNumber(): void
    {
        $body = $this->encryptedBody(['state' => 'SUCCESS']);

        static::assertNull($this->verifier()->outNoOf($this->credentials(), $body, []));
    }

    public function testANotificationWithoutAResourceIsInvalid(): void
    {
        $this->expectExceptionObject(PaymentException::notificationInvalid(
            WechatApi::PROVIDER,
            'the notification carries no encrypted resource'
        ));

        $this->verifier()->outNoOf($this->credentials(), '{"id":"evt-1"}', []);
    }

    public function testAMissingApiV3KeyIsReportedAsMissingCredentials(): void
    {
        $body = $this->encryptedBody(['out_bill_no' => 'PO-1']);

        $this->expectExceptionObject(PaymentException::missingCredentials(WechatApi::PROVIDER, WechatApi::API_V3_KEY));

        $this->verifier()->outNoOf([WechatApi::PLATFORM_CERTS => json_encode([self::SERIAL => $this->publicKey], \JSON_THROW_ON_ERROR)], $body, []);
    }

    /**
     * A resource that cannot be decrypted is an error of this installation, not a bad signature. It has
     * to be logged with the identity of the notification - without the event type nobody can tell a
     * rotated key from a truncated body.
     */
    public function testAnUndecryptableResourceIsLoggedWithTheEventTypeAndThrows(): void
    {
        $logger = static::createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                static::stringContains('could not be decrypted'),
                static::callback(static fn (array $context): bool => ($context['event_type'] ?? null) === 'MCHTRANSFER.BILL.FINISHED'
                    && ($context['resource_type'] ?? null) === 'transfer'
                    && ($context['algorithm'] ?? null) === 'AEAD_AES_256_GCM'
                    && \is_string($context['error'] ?? null))
            );

        $body = json_encode([
            'id' => 'evt-1',
            'event_type' => 'MCHTRANSFER.BILL.FINISHED',
            'resource' => [
                'algorithm' => 'AEAD_AES_256_GCM',
                'original_type' => 'transfer',
                'ciphertext' => 'not-a-ciphertext',
                'nonce' => self::NONCE,
                'associated_data' => 'transfer',
            ],
        ], \JSON_THROW_ON_ERROR);

        try {
            $this->verifier($logger)->outNoOf($this->credentials(), $body, []);
            static::fail('an undecryptable resource has to throw');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::NOTIFICATION_INVALID, $exception->getErrorCode());
        }
    }

    public function testWechatIsAcknowledgedWithItsJsonDocument(): void
    {
        $ack = $this->verifier()->acknowledge();

        static::assertSame('{"code":"SUCCESS","message":"成功"}', $ack->body);
        static::assertSame('application/json; charset=utf-8', $ack->contentType);
    }

    private function verifier(?LoggerInterface $logger = null): WechatTransferNotificationVerifier
    {
        return new WechatTransferNotificationVerifier($logger ?? static::createStub(LoggerInterface::class));
    }

    /**
     * @return array<string, string>
     */
    private function credentials(): array
    {
        return [
            WechatApi::API_V3_KEY => self::API_V3_KEY,
            WechatApi::PLATFORM_CERTS => json_encode([self::SERIAL => $this->publicKey], \JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $timestamp, string $nonce, string $signature, string $serial = self::SERIAL): array
    {
        return [
            WechatNotifyAction::HEADER_TIMESTAMP => $timestamp,
            WechatNotifyAction::HEADER_NONCE => $nonce,
            WechatNotifyAction::HEADER_SIGNATURE => $signature,
            WechatNotifyAction::HEADER_SERIAL => $serial,
        ];
    }

    private function sign(string $body, string $timestamp, string $nonce, ?string $privateKey = null): string
    {
        return Rsa::sign(
            $timestamp . "\n" . $nonce . "\n" . $body . "\n",
            Rsa::from($privateKey ?? $this->privateKey, Rsa::KEY_TYPE_PRIVATE)
        );
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function encryptedBody(array $resource): string
    {
        return json_encode([
            'id' => 'evt-1',
            'event_type' => 'MCHTRANSFER.BILL.FINISHED',
            'resource' => [
                'algorithm' => 'AEAD_AES_256_GCM',
                'original_type' => 'transfer',
                'ciphertext' => AesGcm::encrypt(
                    json_encode($resource, \JSON_THROW_ON_ERROR),
                    self::API_V3_KEY,
                    self::NONCE,
                    'transfer'
                ),
                'nonce' => self::NONCE,
                'associated_data' => 'transfer',
            ],
        ], \JSON_THROW_ON_ERROR);
    }
}
