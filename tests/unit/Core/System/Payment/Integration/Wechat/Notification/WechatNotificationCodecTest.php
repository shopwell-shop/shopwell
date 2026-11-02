<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Integration\Wechat\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Integration\Wechat\Action\WechatNotifyAction;
use Shopwell\Core\System\Payment\Integration\Wechat\Api\WechatApi;
use Shopwell\Core\System\Payment\Integration\Wechat\Notification\WechatNotificationCodec;
use Shopwell\Core\System\Payment\PaymentException;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WechatNotificationCodec::class)]
class WechatNotificationCodecTest extends TestCase
{
    private const API_V3_KEY = '0123456789abcdef0123456789abcdef';

    /**
     * @var array{0: string, 1: string}|null
     */
    private static ?array $keys = null;

    public function testAnUnsignedBodyIsNotVerified(): void
    {
        static::assertFalse(WechatNotificationCodec::verify($this->credentials(), '{}', []));
    }

    public function testASignedBodyIsVerifiedAgainstThePlatformCertificate(): void
    {
        $body = '{"id":"evt-1"}';

        static::assertTrue(WechatNotificationCodec::verify($this->credentials(), $body, $this->signedHeaders($body)));
    }

    public function testABodySignedWithAnotherKeyIsNotVerified(): void
    {
        $body = '{"id":"evt-1"}';
        $headers = $this->signedHeaders($body);

        // a body that was changed after it was signed
        static::assertFalse(WechatNotificationCodec::verify($this->credentials(), '{"id":"evt-2"}', $headers));
    }

    public function testAVerificationWithoutAnyCertificateCannotBeAnswered(): void
    {
        $this->expectExceptionObject(PaymentException::missingCredentials(WechatApi::PROVIDER, WechatApi::PLATFORM_CERTS));

        WechatNotificationCodec::verify([], '{}', $this->signedHeaders('{}'));
    }

    public function testTheResourceIsDecryptedWithTheApiV3Key(): void
    {
        $resource = ['trade_state' => 'SUCCESS', 'amount' => ['total' => 100]];

        $decrypted = WechatNotificationCodec::decryptResource(
            $this->credentials(),
            ['resource' => $this->encryptedResource($resource)]
        );

        static::assertSame($resource, $decrypted);
    }

    public function testAMessageWithoutAnEncryptedResourceIsRejected(): void
    {
        $this->expectExceptionObject(PaymentException::notificationInvalid(WechatApi::PROVIDER, 'the notification carries no encrypted resource'));

        WechatNotificationCodec::decryptResource($this->credentials(), ['id' => 'evt-1']);
    }

    public function testADecryptionWithoutTheKeyIsAConfigurationError(): void
    {
        $this->expectExceptionObject(PaymentException::missingCredentials(WechatApi::PROVIDER, WechatApi::API_V3_KEY));

        WechatNotificationCodec::decryptResource([], ['resource' => $this->encryptedResource(['a' => 1])]);
    }

    public function testABodyThatCannotBeDecryptedIsRejected(): void
    {
        $this->expectException(PaymentException::class);

        WechatNotificationCodec::decryptResource($this->credentials(), [
            'resource' => ['ciphertext' => 'not-a-ciphertext', 'nonce' => 'n', 'associated_data' => 'a'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        return [
            WechatApi::API_V3_KEY => self::API_V3_KEY,
            WechatApi::PLATFORM_CERT => self::keys()[1],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function signedHeaders(string $rawBody): array
    {
        $timestamp = '1700000000';
        $nonce = 'codec-nonce';

        return [
            WechatNotifyAction::HEADER_TIMESTAMP => $timestamp,
            WechatNotifyAction::HEADER_NONCE => $nonce,
            WechatNotifyAction::HEADER_SIGNATURE => Rsa::sign(
                $timestamp . "\n" . $nonce . "\n" . $rawBody . "\n",
                Rsa::from(self::keys()[0], Rsa::KEY_TYPE_PRIVATE)
            ),
        ];
    }

    /**
     * @param array<string, mixed> $resource
     *
     * @return array<string, string>
     */
    private function encryptedResource(array $resource): array
    {
        return [
            'ciphertext' => AesGcm::encrypt(json_encode($resource, \JSON_THROW_ON_ERROR), self::API_V3_KEY, 'codec-nonce', 'codec'),
            'nonce' => 'codec-nonce',
            'associated_data' => 'codec',
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function keys(): array
    {
        if (self::$keys !== null) {
            return self::$keys;
        }

        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($resource);

        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);
        static::assertIsArray($details);

        return self::$keys = [(string) $privateKey, (string) $details['key']];
    }
}
