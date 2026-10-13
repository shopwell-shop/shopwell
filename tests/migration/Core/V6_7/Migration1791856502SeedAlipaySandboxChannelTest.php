<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Migration\V6_7\Migration1791856502SeedAlipaySandboxChannel;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\Provider\Alipay\AlipayApi;
use Shopwell\Core\System\Payment\Service\PaymentCredentialCipher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1791856502SeedAlipaySandboxChannel::class)]
class Migration1791856502SeedAlipaySandboxChannelTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->removeSeededData();
    }

    public function testUpdateSeedsTheInterfaceItsChannelAndTheDemoAppOnlyOnce(): void
    {
        $this->runMigration();

        static::assertSame(1, $this->countRows('payment_provider_config', 'provider_technical_name', Migration1791856502SeedAlipaySandboxChannel::PROVIDER));
        static::assertSame(1, $this->countRows(
            'payment_channel_config',
            'provider_technical_name',
            Migration1791856502SeedAlipaySandboxChannel::PROVIDER,
            'channel_technical_name',
            Migration1791856502SeedAlipaySandboxChannel::CHANNEL
        ));
        static::assertSame(1, $this->countRows('payment_app', 'technical_name', Migration1791856502SeedAlipaySandboxChannel::DEMO_APP_TECHNICAL_NAME));

        static::assertSame(1, (int) $this->scalar(
            'SELECT `enabled` FROM `payment_provider_config` WHERE `provider_technical_name` = :provider',
            ['provider' => Migration1791856502SeedAlipaySandboxChannel::PROVIDER]
        ));
        static::assertSame(1, (int) $this->scalar(
            'SELECT `enabled` FROM `payment_channel_config` WHERE `provider_technical_name` = :provider AND `channel_technical_name` = :channel',
            ['provider' => Migration1791856502SeedAlipaySandboxChannel::PROVIDER, 'channel' => Migration1791856502SeedAlipaySandboxChannel::CHANNEL]
        ));
    }

    public function testTheSandboxCredentialsAreSealedOnTheInterface(): void
    {
        $this->runMigration();

        $stored = $this->scalar(
            'SELECT `credentials` FROM `payment_provider_config` WHERE `provider_technical_name` = :provider',
            ['provider' => Migration1791856502SeedAlipaySandboxChannel::PROVIDER]
        );

        static::assertIsString($stored);
        // the credentials belong to the interface, and nothing of them is stored in plain text
        static::assertStringNotContainsString(Migration1791856502SeedAlipaySandboxChannel::SANDBOX_APP_ID, $stored);
        static::assertStringNotContainsString(Migration1791856502SeedAlipaySandboxChannel::SANDBOX_PRIVATE_KEY, $stored);

        $decoded = json_decode($stored, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        $credentials = $this->cipher()->decrypt($decoded);

        static::assertSame(Migration1791856502SeedAlipaySandboxChannel::SANDBOX_APP_ID, $credentials[AlipayApi::APP_ID] ?? null);
        static::assertSame(Migration1791856502SeedAlipaySandboxChannel::SANDBOX_PRIVATE_KEY, $credentials[AlipayApi::PRIVATE_KEY] ?? null);
        static::assertSame(Migration1791856502SeedAlipaySandboxChannel::SANDBOX_ALIPAY_PUBLIC_KEY, $credentials[AlipayApi::PUBLIC_KEY] ?? null);
        static::assertSame(Migration1791856502SeedAlipaySandboxChannel::SANDBOX_GATEWAY_URL, $credentials[AlipayApi::GATEWAY_URL] ?? null);
    }

    public function testTheDemoAppCollectsThroughTheInterfaceWithTheCredentialsOfThePlatform(): void
    {
        $this->runMigration();

        $appId = $this->scalar('SELECT `id` FROM `payment_app` WHERE `technical_name` = :name', [
            'name' => Migration1791856502SeedAlipaySandboxChannel::DEMO_APP_TECHNICAL_NAME,
        ]);

        static::assertIsString($appId);

        static::assertSame(PaymentAppProviderEntity::MODE_PLATFORM, $this->scalar(
            'SELECT `mode` FROM `payment_app_provider` WHERE `payment_app_id` = :app',
            ['app' => $appId]
        ));
        static::assertSame(1, (int) $this->scalar(
            'SELECT `enabled` FROM `payment_app_provider` WHERE `payment_app_id` = :app',
            ['app' => $appId]
        ));
        static::assertSame(1, $this->countRows('payment_app_channel', 'payment_app_id', $appId, 'channel_technical_name', Migration1791856502SeedAlipaySandboxChannel::CHANNEL));
        static::assertSame(1, (int) $this->scalar('SELECT `active` FROM `payment_app` WHERE `id` = :id', ['id' => $appId]));

        $storedSecret = $this->scalar('SELECT `app_secret` FROM `payment_app` WHERE `id` = :id', ['id' => $appId]);
        static::assertIsString($storedSecret);
        static::assertStringNotContainsString(Migration1791856502SeedAlipaySandboxChannel::DEMO_APP_SECRET, $storedSecret);
        static::assertSame(Migration1791856502SeedAlipaySandboxChannel::DEMO_APP_SECRET, $this->cipher()->decryptValue($storedSecret));
    }

    public function testUpdateKeepsAnAlreadySeededInterfaceUsable(): void
    {
        $this->runMigration();

        $seeded = $this->scalar(
            'SELECT `created_at` FROM `payment_provider_config` WHERE `provider_technical_name` = :provider',
            ['provider' => Migration1791856502SeedAlipaySandboxChannel::PROVIDER]
        );

        $this->runMigration();

        $afterSecondRun = $this->scalar(
            'SELECT `created_at` FROM `payment_provider_config` WHERE `provider_technical_name` = :provider',
            ['provider' => Migration1791856502SeedAlipaySandboxChannel::PROVIDER]
        );

        // the second run updates what it found instead of inserting a second interface
        static::assertSame($seeded, $afterSecondRun);
        static::assertSame(1, $this->countRows('payment_provider_config', 'provider_technical_name', Migration1791856502SeedAlipaySandboxChannel::PROVIDER));
    }

    private function runMigration(): void
    {
        $migration = new Migration1791856502SeedAlipaySandboxChannel();
        $migration->update($this->connection);
    }

    private function cipher(): PaymentCredentialCipher
    {
        $secret = $_SERVER['APP_SECRET'] ?? $_ENV['APP_SECRET'] ?? getenv('APP_SECRET');

        if (!\is_string($secret) || $secret === '') {
            static::markTestSkipped('The environment variable APP_SECRET is required to unseal the seeded credentials.');
        }

        return new PaymentCredentialCipher($secret);
    }

    private function removeSeededData(): void
    {
        $appId = $this->scalar('SELECT `id` FROM `payment_app` WHERE `technical_name` = :name', [
            'name' => Migration1791856502SeedAlipaySandboxChannel::DEMO_APP_TECHNICAL_NAME,
        ]);

        if (\is_string($appId)) {
            $this->connection->delete('payment_app_channel', ['payment_app_id' => $appId]);
            $this->connection->delete('payment_app_provider', ['payment_app_id' => $appId]);
            $this->connection->delete('payment_app', ['id' => $appId]);
        }

        $this->connection->delete('payment_channel_config', [
            'provider_technical_name' => Migration1791856502SeedAlipaySandboxChannel::PROVIDER,
            'channel_technical_name' => Migration1791856502SeedAlipaySandboxChannel::CHANNEL,
        ]);
        $this->connection->delete('payment_provider_config', ['provider_technical_name' => Migration1791856502SeedAlipaySandboxChannel::PROVIDER]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function scalar(string $query, array $parameters): mixed
    {
        return $this->connection->fetchOne($query, $parameters);
    }

    private function countRows(string $table, string $column, string $value, ?string $secondColumn = null, ?string $secondValue = null): int
    {
        $where = \sprintf('`%s` = :value', $column);
        $parameters = ['value' => $value];

        if ($secondColumn !== null && $secondValue !== null) {
            $where .= \sprintf(' AND `%s` = :secondValue', $secondColumn);
            $parameters['secondValue'] = $secondValue;
        }

        return (int) $this->connection->fetchOne(\sprintf('SELECT COUNT(*) FROM `%s` WHERE %s', $table, $where), $parameters);
    }
}
