<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationException;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayApi;

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
 * production, and the demo app has to be removed. The seeded key pair is a locally generated
 * placeholder: it is complete and signs and verifies against itself, but it belongs to no alipay app,
 * so both keys have to be swapped for the ones of the real sandbox app in the alipay console.
 *
 * @internal
 */
#[Package('framework')]
class Migration1791856502SeedAlipaySandboxChannel extends MigrationStep
{
    final public const PROVIDER = AlipayApi::PROVIDER;

    final public const CHANNEL = AlipayApi::CHANNEL_H5;

    /**
     * The app id of the sandbox app of the alipay open platform.
     */
    final public const SANDBOX_APP_ID = '9021000149657375';

    final public const SANDBOX_GATEWAY_URL = 'https://openapi-sandbox.dl.alipaydev.com';

    /**
     * The private key of the sandbox app, used to sign the requests: the pkcs#8 body without the pem
     * envelope, the way `AlipayApi::wrapPem()` expects it.
     */
    final public const SANDBOX_PRIVATE_KEY = 'MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQCx1O874e6cAzTwOqU2Ri/8ZaGu1WdvjZ8MaiMgNAIR1SZh7yJ/+HcC2ijpMPq0x1+FoCWz0u/haCHNJFWL36fPaLILJTOicN9EwqdQCT0JbYQw1TOL+V+G9/tq2YFuizwVyRJ+ua2qC0JZoT9cqfVZTxTQJ++H++pJ9XjJtTHyEXwAl89xYI1KjYu1S5LmPtuLjpx+xmcg9S+vZdYxFW3yvRHrsvZI4ECSNi0A27cpbPSqucq7w2cjfhistPjTdew4+h05p2liC9XYqfnNRfv8r3iufyP/ovVMFUEDtScIveyX2Lb+g1l6Afp/5o369vI3JLHUAI+cYF6UtD6WtiZtAgMBAAECggEABMed5ISmlq/pYNmarjJFwOl0ZpAa1x892qYRqqBrO9J4VSKJ0Vm6zStS9S2ZV3mSYjOTP9mYE/1qBysGV5ub1ZHRU0ZdNG0HO0BmIdsVtmaoqm1YlNcPj7+jlNQiy/dXFpjQc/KGre7eii0w2EtDKnLbMtsX75ZATluYMwe5P3HYSnmx63jtO+MRlCcLO/orrmMzxS1hQ/jg9CnxDoGlsDFheO7bUROx/VH110iB9X61j08FQyiRex2e0Y5z/nvBByYPz3o+fDnNuIwRU+MSGX/fhRQ83X49EzjB1xSYMwXgK7fJdVziqySXMrwsetGCAgMqR32XjQ6sr+jmzbzE4QKBgQDzzQLOXrB0EHD74XUe060y9U8Zv14dq23KDLnhiHQPbmLUrqheTDbOIWLcuNr3tEHvS0MGlYJdkKZpRRK404rykq9u7BR+8F8eHsBdEBK10eSE/YdzuFjB2/IpkZ/NzaCZqo0Uj+9m4en1yS6bJPzhq2zkrBSuOw93XjD82Rkv/QKBgQC6uuMP31X2qlXmVvsX224HjVPC7b78Kidqt0BOaG/tsOLmES0lZ0KdrKLlIxxl7wDd/FYzqiqQRLZp+9Tj8SF4+TCZlnMFaEJFTMzIRa45zcj8tW9y/PAond4yIy2mmjj7NM3l1/chmr6pwxcb5JWX/bRqmV7nUSbLXq8LAOQDMQKBgQCmxRAsVr1hR3Xh9sGFCsspVoB7Xlpk9UUutzHXwt2RSLM2RRtLFdtPeasEzTaLxrRrmhIJSPBaTH5o+CTsZVhgwlJbEAadJpQKKILxpfMGiMNG7Z5gXDPquCeYo9KqlwRK2U2USTk8KUE/LhVBbzXEsNzWN5gV7rSPtNDRxTfjiQKBgQCD3vX6QKRJ9hOB/02GlK3I4ZMU56foF7EMmWvts63QNAqZpem3L4yJI+FKZxhoGHkJTGpoRDWY+x3EDVhUvfcAoyMiZHDIkc+zd0Br82HOarkVVuDlWSIV3R+NUvCrZuD07161gmaCMfGSnDdWuIC4/TAL1FKEk/gMycpev/tHEQKBgQCqwyrCe/VvYcBkD3LWeKfcH19QHv4gcc8fyPTYSFlqUeXJLi0wO0qs+SbrLJBkdf1LoltmyWu4X6Z5OtyRXf90NA3xP0FXFXUIE+SSveG/5XcCWG3SR31yAE9ZcvzfGydD+MYFOc28Q2XhCCXbVxQkHKY4fGLFiuIetrjbg+35/w==';

    /**
     * The public key of alipay itself, used to verify the notifications: the spki body without the pem
     * envelope.
     */
    final public const SANDBOX_ALIPAY_PUBLIC_KEY = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAsdTvO+HunAM08DqlNkYv/GWhrtVnb42fDGojIDQCEdUmYe8if/h3Atoo6TD6tMdfhaAls9Lv4WghzSRVi9+nz2iyCyUzonDfRMKnUAk9CW2EMNUzi/lfhvf7atmBbos8FckSfrmtqgtCWaE/XKn1WU8U0Cfvh/vqSfV4ybUx8hF8AJfPcWCNSo2LtUuS5j7bi46cfsZnIPUvr2XWMRVt8r0R67L2SOBAkjYtANu3KWz0qrnKu8NnI34YrLT403XsOPodOadpYgvV2Kn5zUX7/K94rn8j/6L1TBVBA7UnCL3sl9i2/oNZegH6f+aN+vbyNySx1ACPnGBelLQ+lrYmbQIDAQAB';

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
