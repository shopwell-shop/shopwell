<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Wechat;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Provider\Wechat\WechatApi;
use WeChatPay\Crypto\Rsa;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WechatApi::class)]
class WechatApiTest extends TestCase
{
    public function testExposesConfigurationAndChannelParameter(): void
    {
        $api = $this->api(
            credentials: [WechatApi::MCH_ID => '1900000001'],
            params: [WechatApi::PARAM_APP_ID => 'wx-app-id'],
        );

        static::assertSame('wechat', $api->provider());
        static::assertSame('wechat_h5', $api->channel());
        static::assertSame('live', $api->mode());
        static::assertSame('wx-app-id', $api->appId());
        static::assertSame('https://example.com/notify', $api->notifyUrl());
        static::assertSame('https://example.com/return', $api->returnUrl());
    }

    public function testAppIdThrowsWhenChannelParameterIsMissing(): void
    {
        $api = $this->api();

        $this->expectExceptionObject(PaymentException::channelNotConfigured('wechat_h5', WechatApi::PARAM_APP_ID));

        $api->appId();
    }

    public function testApiV3KeyReturnsCredential(): void
    {
        $api = $this->api(credentials: [WechatApi::API_V3_KEY => 'a-256-bit-api-v3-key-value-123']);

        static::assertSame('a-256-bit-api-v3-key-value-123', $api->apiV3Key());
    }

    public function testApiV3KeyThrowsWhenCredentialIsMissing(): void
    {
        $api = $this->api();

        $this->expectExceptionObject(PaymentException::missingCredentials('wechat', WechatApi::API_V3_KEY));

        $api->apiV3Key();
    }

    public function testPlatformCertOfReturnsTheSingleCertificateForAnUnknownSerial(): void
    {
        $api = $this->api(credentials: [
            WechatApi::PLATFORM_CERT => 'CERT-PEM',
            WechatApi::PLATFORM_SERIAL => 'PLATFORM-SERIAL',
        ]);

        // the channel rotates its certificates, so the one we have is used for any serial
        static::assertSame('CERT-PEM', $api->platformCertOf(null));
        static::assertSame('CERT-PEM', $api->platformCertOf('UNKNOWN-SERIAL'));
        static::assertSame('CERT-PEM', $api->platformCertOf('PLATFORM-SERIAL'));
    }

    public function testPlatformCertOfReturnsTheCertificateOfTheKnownSerial(): void
    {
        $api = $this->api(credentials: [
            WechatApi::PLATFORM_CERTS => json_encode(['SERIAL-A' => 'CERT-A', 'SERIAL-B' => 'CERT-B'], \JSON_THROW_ON_ERROR),
        ]);

        static::assertSame('CERT-A', $api->platformCertOf('SERIAL-A'));
        static::assertSame('CERT-B', $api->platformCertOf('SERIAL-B'));
    }

    public function testPlatformCertOfReturnsNullForAnUnknownSerialWhenSeveralCertificatesAreConfigured(): void
    {
        $api = $this->api(credentials: [
            WechatApi::PLATFORM_CERTS => ['SERIAL-A' => 'CERT-A', 'SERIAL-B' => 'CERT-B'],
        ]);

        static::assertNull($api->platformCertOf('UNKNOWN-SERIAL'));
        static::assertNull($api->platformCertOf(null));
    }

    public function testPlatformCertOfNormalizesConfiguredCertificates(): void
    {
        $api = $this->api(credentials: [
            // scalars stay, empty and non-scalar entries are dropped
            WechatApi::PLATFORM_CERTS => ['SERIAL-A' => 'CERT-A', 'SERIAL-B' => '', 'SERIAL-C' => null, 'SERIAL-D' => 123],
            WechatApi::PLATFORM_CERT => 'SINGLE-CERT',
        ]);

        static::assertSame('CERT-A', $api->platformCertOf('SERIAL-A'));
        static::assertSame('123', $api->platformCertOf('SERIAL-D'));
        static::assertNull($api->platformCertOf('SERIAL-B'));
        // default serial name when none is configured
        static::assertSame('SINGLE-CERT', $api->platformCertOf('platform'));
    }

    public function testPlatformCertOfThrowsWhenNoCertificateIsConfigured(): void
    {
        $api = $this->api();

        $this->expectExceptionObject(PaymentException::missingCredentials('wechat', WechatApi::PLATFORM_CERTS));

        $api->platformCertOf(null);
    }

    public function testMerchantPrivateKeyAcceptsAPemAndSignsAMessage(): void
    {
        [$privateKey, $publicKey] = $this->keyPair();

        $api = $this->api(credentials: [WechatApi::MCH_PRIVATE_KEY => $privateKey]);

        $signature = $api->sign('the-message-to-sign');

        static::assertTrue(Rsa::verify('the-message-to-sign', $signature, Rsa::from($publicKey, Rsa::KEY_TYPE_PUBLIC)));
    }

    public function testMerchantPrivateKeyWrapsARawPkcs8Body(): void
    {
        [$privateKey] = $this->keyPair();
        $rawBody = preg_replace('/\s+/', '', (string) preg_replace('#-----(BEGIN|END) PRIVATE KEY-----#', '', $privateKey));

        $api = $this->api(credentials: [WechatApi::MCH_PRIVATE_KEY => $rawBody]);

        // the raw pkcs8 body was wrapped into a pem that can be used to sign
        static::assertNotSame('', $api->sign('the-message-to-sign'));
    }

    public function testPublicKeyOfReturnsTheKeyOfACertificate(): void
    {
        [$privateKey, $publicKey] = $this->keyPair();

        $api = $this->api();

        openssl_sign('the-message-to-verify', $signature, $privateKey, \OPENSSL_ALGO_SHA256);

        // the returned key verifies the signature that its private counterpart created
        static::assertSame(1, openssl_verify('the-message-to-verify', $signature, $api->publicKeyOf($publicKey), \OPENSSL_ALGO_SHA256));
    }

    public function testBuilderBuildsTheSdkClientOnce(): void
    {
        [$privateKey] = $this->keyPair();

        $api = $this->api(credentials: [
            WechatApi::MCH_ID => '1900000001',
            WechatApi::MCH_SERIAL => 'MCH-SERIAL',
            WechatApi::MCH_PRIVATE_KEY => $privateKey,
            WechatApi::PLATFORM_CERT => 'CERT-PEM',
            WechatApi::PLATFORM_SERIAL => 'PLATFORM-SERIAL',
        ]);

        $builder = $api->builder();

        static::assertSame($builder, $api->builder());
    }

    /**
     * @return array{string, string}
     */
    private function keyPair(): array
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        static::assertNotFalse($resource);

        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);
        static::assertIsArray($details);

        return [(string) $privateKey, (string) $details['key']];
    }

    /**
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $params
     */
    private function api(array $credentials = [], array $params = []): WechatApi
    {
        $channel = new PaymentChannel('wechat_h5', 'wechat', 'wechat_h5', PaymentFlow::REDIRECT);

        return new WechatApi(
            new ResolvedPaymentConfig(
                provider: new PaymentProvider(
                    'wechat',
                    'wechat',
                    [],
                    ['wechat_h5' => $channel],
                    new PaymentGatewayFactory('wechat', WechatApi::class, []),
                    static::createStub(PaymentNotificationExtractorInterface::class),
                ),
                channel: $channel,
                credentials: $credentials,
                params: $params,
                runtimeParams: [],
                settings: [],
                mode: 'live',
            ),
            'https://example.com/notify',
            'https://example.com/return',
        );
    }
}
