<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1793831400EnableWishlistByDefault;

/**
 * The wishlist is on by default in the Shopwell baseline, and the default lives in one global row:
 * a row for a single sales channel keeps overriding it, because a merchant that switched the
 * feature off for one channel did so on purpose.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1793831400EnableWishlistByDefault::class)]
class Migration1793831400EnableWishlistByDefaultTest extends TestCase
{
    private const CONFIG_KEY = 'core.cart.wishlistEnabled';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->deleteGlobalRow();
    }

    protected function tearDown(): void
    {
        $this->deleteGlobalRow();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1793831400, (new Migration1793831400EnableWishlistByDefault())->getCreationTimestamp());
    }

    public function testTheMigrationInsertsTheGlobalDefaultRow(): void
    {
        $migration = new Migration1793831400EnableWishlistByDefault();
        $migration->update($this->connection);

        static::assertTrue(
            $this->globalValueIsEnabled(),
            'A fresh installation must find the wishlist enabled without touching any setting',
        );
    }

    public function testTheMigrationIsIdempotent(): void
    {
        $migration = new Migration1793831400EnableWishlistByDefault();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(1, $this->countGlobalRows());
    }

    public function testTheMigrationTurnsAnOffGlobalDefaultBackOn(): void
    {
        // upstream left the global row switched off, an update has to reach
        // the Shopwell baseline
        $this->insertGlobalRow('{"_value":false}');

        $migration = new Migration1793831400EnableWishlistByDefault();
        $migration->update($this->connection);

        static::assertTrue($this->globalValueIsEnabled());
        static::assertSame(1, $this->countGlobalRows());
    }

    public function testTheMigrationLeavesChannelSpecificValuesAlone(): void
    {
        // a merchant that switched the wishlist off for one sales channel did
        // so on purpose, the channel row keeps winning over the global default
        $salesChannelId = (string) $this->connection->fetchOne('SELECT id FROM sales_channel LIMIT 1');
        static::assertNotSame('', $salesChannelId);

        $this->connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::CONFIG_KEY,
            'configuration_value' => '{"_value":false}',
            'sales_channel_id' => $salesChannelId,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
        ]);

        $migration = new Migration1793831400EnableWishlistByDefault();
        $migration->update($this->connection);

        $channelValue = $this->connection->fetchOne('
            SELECT configuration_value
            FROM system_config
            WHERE configuration_key = :configurationKey
                AND sales_channel_id = :salesChannelId;
        ', ['configurationKey' => self::CONFIG_KEY, 'salesChannelId' => $salesChannelId]);

        static::assertTrue($this->globalValueIsEnabled());
        static::assertFalse((bool) json_decode((string) $channelValue, true, 512, \JSON_THROW_ON_ERROR)['_value']);

        $this->connection->delete('system_config', [
            'configuration_key' => self::CONFIG_KEY,
            'sales_channel_id' => $salesChannelId,
        ]);
    }

    private function globalValueIsEnabled(): bool
    {
        $value = json_decode($this->fetchGlobalValue(), true, 512, \JSON_THROW_ON_ERROR);

        return ($value['_value'] ?? null) === true;
    }

    private function fetchGlobalValue(): string
    {
        return (string) $this->connection->fetchOne('
            SELECT configuration_value
            FROM system_config
            WHERE configuration_key = :configurationKey
                AND sales_channel_id IS NULL;
        ', ['configurationKey' => self::CONFIG_KEY]);
    }

    private function countGlobalRows(): int
    {
        return (int) $this->connection->fetchOne('
            SELECT COUNT(*)
            FROM system_config
            WHERE configuration_key = :configurationKey
                AND sales_channel_id IS NULL;
        ', ['configurationKey' => self::CONFIG_KEY]);
    }

    private function insertGlobalRow(string $value): void
    {
        $this->connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::CONFIG_KEY,
            'configuration_value' => $value,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
        ]);
    }

    private function deleteGlobalRow(): void
    {
        // rows of every scope, so a failed run of the channel test does not
        // leave a duplicate behind
        $this->connection->executeStatement('
            DELETE FROM system_config
            WHERE configuration_key = :configurationKey;
        ', ['configurationKey' => self::CONFIG_KEY]);
    }
}
