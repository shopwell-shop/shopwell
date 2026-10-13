<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationException;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayApi;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayGatewayFactory;
use Shopwell\Core\System\Payment\Service\PaymentCredentialCipher;

/**
 * Seeds the alipay interface with the public sandbox account of the alipay open platform, plus one demo
 * app that may collect through it, so the payment api can be called right away.
 *
 * The credentials are written where they belong in the model: on the interface and not on a single
 * product of it, so the demo app can collect through every channel alipay is declared with. Everything
 * is written the very same way the application writes it - the credentials are sealed with the same
 * cipher, so nothing is stored in plain text. The cipher key is derived from APP_SECRET, so this
 * migration needs that environment variable, which every installation has.
 *
 * ⚠️ The credentials below are alipay's *public sandbox* test account: they are deliberately not a
 * secret, but they must be replaced with the real merchant credentials before this channel is used in
 * production, and the demo app has to be removed. If signing fails with a key format error, the private
 * key stored below is incomplete and has to be replaced with the complete pkcs#8 key of the sandbox app.
 *
 * @internal
 */
#[Package('framework')]
class Migration1791856502SeedAlipaySandboxChannel extends MigrationStep
{
    final public const PROVIDER = AlipayGatewayFactory::PROVIDER;

    final public const CHANNEL = AlipayGatewayFactory::CHANNEL_H5;

    /**
     * The app id of the sandbox app of the alipay open platform.
     */
    final public const SANDBOX_APP_ID = '9021000149657375';

    final public const SANDBOX_GATEWAY_URL = 'https://openapi-sandbox.dl.alipaydev.com';

    /**
     * The private key of the sandbox app, used to sign the requests.
     */
    final public const SANDBOX_PRIVATE_KEY = 'MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQC1u2gxfwsoO6u9Fy8DEEYJQRof8J2wPsFxqQsu4Q9ipSJ8y+t2Gap43BUW3DLVBFn3pClg5a/lrzBOYy8VtlH/1t9Eli9rq1xIuG11BaBqrR2Yw0HPknZ5uNp9z2Xo3kWiSLSJgT85yA01W1f5t5m9j7ZeC23PdvUYVvw2Or7YBiMlPS1DeU3LmLQkLmhk5kOmFDw1vUvVmM2uSxs0HIAgXUraDlVyZOvTB7m3lt7CJI/vxWp2TdGRBpv8XjWCttgULQtCzQn1NGJv6ARpD8No0SNvRXWZogXZBVJnGkwtFwYvXHl6lrIVnhGQcbfPOI2v2Q6mVKklyWfhw6C/bFLfAgMBAAECggEAA2xw8GjSkZB/eUxatjrHknw2EjHOnUQNk+XvAL0WvK0hbGKq6uXcWtCLqQBFHwcQ9eFCgO5CA7MwsdWOs4BkWMOB7M6tI';

    /**
     * The public key of alipay itself, used to verify the notifications.
     */
    final public const SANDBOX_ALIPAY_PUBLIC_KEY = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA4QFG';

    final public const DEMO_APP_TECHNICAL_NAME = 'alipay_sandbox_demo';

    final public const DEMO_APP_SECRET = 'sandbox-only-app-secret';

    public function getCreationTimestamp(): int
    {
        return 1791856502;
    }

    public function update(Connection $connection): void
    {
        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $cipher = $this->cipher();

        $this->seedProviderConfig($connection, $cipher, $now);
        $this->seedChannelConfig($connection, $now);
        $this->seedDemoApp($connection, $cipher, $now);
    }

    public function updateDestructive(Connection $connection): void
    {
        // the seeded credentials are kept: removing them would break a running installation
    }

    private function seedProviderConfig(Connection $connection, PaymentCredentialCipher $cipher, string $now): void
    {
        $existing = $connection->fetchOne(
            'SELECT `id` FROM `payment_provider_config` WHERE `provider_technical_name` = :provider',
            ['provider' => self::PROVIDER]
        );

        $payload = (string) json_encode($cipher->encrypt($this->sandboxCredentials()), \JSON_THROW_ON_ERROR);
        $settings = (string) json_encode(['http_method' => 'GET'], \JSON_THROW_ON_ERROR);

        if (\is_string($existing) && $existing !== '') {
            $connection->update('payment_provider_config', [
                'enabled' => 1,
                'credentials' => $payload,
                'settings' => $settings,
                'updated_at' => $now,
            ], ['id' => $existing]);

            return;
        }

        $connection->insert('payment_provider_config', [
            'id' => Uuid::randomBytes(),
            'provider_technical_name' => self::PROVIDER,
            'enabled' => 1,
            'credentials' => $payload,
            'settings' => $settings,
            'created_at' => $now,
        ]);
    }

    /**
     * A configured interface does not offer a channel by itself - the platform decides here which of
     * the channels of an interface an app may collect through at all.
     *
     * The h5 channel declares no parameter of its own, so switching it on is everything that is needed
     * for the demo app to work out of the box.
     */
    private function seedChannelConfig(Connection $connection, string $now): void
    {
        $existing = $connection->fetchOne(
            'SELECT `id` FROM `payment_channel_config` WHERE `provider_technical_name` = :provider AND `channel_technical_name` = :channel',
            ['provider' => self::PROVIDER, 'channel' => self::CHANNEL]
        );

        if (\is_string($existing) && $existing !== '') {
            $connection->update('payment_channel_config', [
                'enabled' => 1,
                'updated_at' => $now,
            ], ['id' => $existing]);

            return;
        }

        $connection->insert('payment_channel_config', [
            'id' => Uuid::randomBytes(),
            'provider_technical_name' => self::PROVIDER,
            'channel_technical_name' => self::CHANNEL,
            'enabled' => 1,
            'created_at' => $now,
        ]);
    }

    private function seedDemoApp(Connection $connection, PaymentCredentialCipher $cipher, string $now): void
    {
        $appId = $connection->fetchOne(
            'SELECT `id` FROM `payment_app` WHERE `technical_name` = :name',
            ['name' => self::DEMO_APP_TECHNICAL_NAME]
        );

        if (!\is_string($appId) || $appId === '') {
            $appId = Uuid::randomBytes();

            $connection->insert('payment_app', [
                'id' => $appId,
                'technical_name' => self::DEMO_APP_TECHNICAL_NAME,
                'name' => '支付宝沙箱联调应用',
                'app_secret' => $cipher->encryptValue(self::DEMO_APP_SECRET),
                'active' => 1,
                'created_at' => $now,
            ]);
        }

        $this->seedAppProvider($connection, $appId, $now);
        $this->seedAppChannel($connection, $appId, $now);
    }

    /**
     * The app is authorized for the whole interface, so it can collect through every channel of alipay
     * the platform has configured - and it pays with the account of the platform.
     */
    private function seedAppProvider(Connection $connection, string $appId, string $now): void
    {
        $binding = $connection->fetchOne(
            'SELECT `id` FROM `payment_app_provider` WHERE `payment_app_id` = :app AND `provider_technical_name` = :provider',
            ['app' => $appId, 'provider' => self::PROVIDER]
        );

        if (\is_string($binding) && $binding !== '') {
            return;
        }

        $connection->insert('payment_app_provider', [
            'id' => Uuid::randomBytes(),
            'payment_app_id' => $appId,
            'provider_technical_name' => self::PROVIDER,
            'enabled' => 1,
            // the demo app pays with the credentials of the platform, so it needs no own alipay account
            'mode' => PaymentAppProviderEntity::MODE_PLATFORM,
            'created_at' => $now,
        ]);
    }

    private function seedAppChannel(Connection $connection, string $appId, string $now): void
    {
        $binding = $connection->fetchOne(
            'SELECT `id` FROM `payment_app_channel` WHERE `payment_app_id` = :app AND `channel_technical_name` = :channel',
            ['app' => $appId, 'channel' => self::CHANNEL]
        );

        if (\is_string($binding) && $binding !== '') {
            return;
        }

        $connection->insert('payment_app_channel', [
            'id' => Uuid::randomBytes(),
            'payment_app_id' => $appId,
            'channel_technical_name' => self::CHANNEL,
            'enabled' => 1,
            'created_at' => $now,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function sandboxCredentials(): array
    {
        return [
            AlipayApi::APP_ID => self::SANDBOX_APP_ID,
            AlipayApi::PRIVATE_KEY => self::SANDBOX_PRIVATE_KEY,
            AlipayApi::PUBLIC_KEY => self::SANDBOX_ALIPAY_PUBLIC_KEY,
            AlipayApi::GATEWAY_URL => self::SANDBOX_GATEWAY_URL,
        ];
    }

    private function cipher(): PaymentCredentialCipher
    {
        $secret = $_SERVER['APP_SECRET'] ?? $_ENV['APP_SECRET'] ?? getenv('APP_SECRET');

        if (!\is_string($secret) || $secret === '') {
            throw MigrationException::migrationError('The environment variable APP_SECRET is required to seal the seeded payment credentials.');
        }

        return new PaymentCredentialCipher($secret);
    }
}
