<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_7\Migration1765205483AddTrackOffcanvasCartToAnalytics;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1765205483AddTrackOffcanvasCartToAnalytics::class)]
class Migration1765205483AddTrackOffcanvasCartToAnalyticsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1765205483, (new Migration1765205483AddTrackOffcanvasCartToAnalytics())->getCreationTimestamp());
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1765205483AddTrackOffcanvasCartToAnalytics();
        static::assertSame(1765205483, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();

        $migration = new Migration1765205483AddTrackOffcanvasCartToAnalytics();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_analytics', 'track_offcanvas_cart'));
    }

    private function rollback(): void
    {
        if (TableHelper::columnExists($this->connection, 'sales_channel_analytics', 'track_offcanvas_cart')) {
            $this->connection->executeStatement('ALTER TABLE `sales_channel_analytics` DROP COLUMN `track_offcanvas_cart`;');
        }
    }
}
