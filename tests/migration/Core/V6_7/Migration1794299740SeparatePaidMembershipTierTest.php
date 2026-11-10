<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1794299740SeparatePaidMembershipTier;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1794299740SeparatePaidMembershipTier::class)]
class Migration1794299740SeparatePaidMembershipTierTest extends TestCase
{
    private Connection $shared;

    private Connection $connection;

    private string $database;

    protected function setUp(): void
    {
        $this->shared = KernelLifecycleManager::getConnection();
        $this->database = 'membership_tier_' . Uuid::randomHex();
        $this->shared->executeStatement('CREATE DATABASE `' . $this->database . '`');
        $this->connection = new Connection(array_replace($this->shared->getParams(), ['dbname' => $this->database]), $this->shared->getDriver(), $this->shared->getConfiguration());
        $this->connection->executeStatement('CREATE TABLE loyalty_tier (id BINARY(16) NOT NULL PRIMARY KEY)');
        $this->connection->executeStatement('CREATE TABLE loyalty_account (
            id BINARY(16) NOT NULL PRIMARY KEY, customer_id BINARY(16) NOT NULL,
            tier_id BINARY(16) NULL, tier_points INT NOT NULL, membership_paid_until DATETIME(3) NULL
        )');
        $this->connection->executeStatement('CREATE TABLE membership_subscription (
            id BINARY(16) NOT NULL PRIMARY KEY, customer_id BINARY(16) NOT NULL,
            loyalty_tier_id BINARY(16) NULL, state VARCHAR(20) NOT NULL,
            starts_at DATETIME(3) NOT NULL, ends_at DATETIME(3) NOT NULL
        )');
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        $this->shared->executeStatement('DROP DATABASE `' . $this->database . '`');
    }

    public function testAddsNullablePaidTierWithForeignKeyIdempotently(): void
    {
        $migration = new Migration1794299740SeparatePaidMembershipTier();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $table = $this->connection->createSchemaManager()->introspectTableByUnquotedName('loyalty_account');
        static::assertFalse($table->getColumn('paid_tier_id')->getNotnull());
        static::assertSame('SET NULL', $table->getForeignKey('fk.loyalty_account.paid_tier_id')->getOnDeleteAction()->value);
    }

    public function testBackfillsOnlyRunningSubscriptionsAndPreservesEarnedTierPoints(): void
    {
        $paidTier = Uuid::randomBytes();
        $earnedTier = Uuid::randomBytes();
        $this->connection->insert('loyalty_tier', ['id' => $paidTier]);
        $this->connection->insert('loyalty_tier', ['id' => $earnedTier]);

        $ids = [];
        foreach (['active', 'expired', 'ended', 'future', 'free'] as $scenario) {
            $id = Uuid::randomBytes();
            $this->connection->insert('loyalty_account', [
                'id' => $id, 'customer_id' => $id, 'tier_id' => $earnedTier, 'tier_points' => 5000,
            ]);
            if ($scenario !== 'free') {
                $this->connection->insert('membership_subscription', [
                    'id' => Uuid::randomBytes(), 'customer_id' => $id, 'loyalty_tier_id' => $paidTier,
                    'state' => $scenario === 'ended' ? 'ended' : 'active',
                    'starts_at' => (new \DateTimeImmutable($scenario === 'future' ? '+1 day' : '-30 days', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
                    'ends_at' => (new \DateTimeImmutable($scenario === 'expired' ? '-1 day' : '+30 days', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
                ]);
            }
            if ($scenario === 'active') {
                // A future renewal must not hide the qualification that is running today.
                $this->connection->insert('membership_subscription', [
                    'id' => Uuid::randomBytes(), 'customer_id' => $id, 'loyalty_tier_id' => $earnedTier,
                    'state' => 'active',
                    'starts_at' => (new \DateTimeImmutable('+31 days', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
                    'ends_at' => (new \DateTimeImmutable('+60 days', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
                ]);
            }
            $ids[$scenario] = $id;
        }

        $migration = new Migration1794299740SeparatePaidMembershipTier();
        $migration->update($this->connection);
        foreach ($ids as $scenario => $id) {
            $row = $this->connection->fetchAssociative('SELECT * FROM loyalty_account WHERE id = ?', [$id]);
            static::assertIsArray($row);
            static::assertSame($scenario === 'active' ? $paidTier : null, $row['paid_tier_id']);
            static::assertSame($earnedTier, $row['tier_id']);
            static::assertSame(5000, (int) $row['tier_points']);
        }

        $this->connection->update('loyalty_account', ['paid_tier_id' => $earnedTier], ['id' => $ids['active']]);
        $migration->update($this->connection);
        static::assertSame($earnedTier, $this->connection->fetchOne('SELECT paid_tier_id FROM loyalty_account WHERE id = ?', [$ids['active']]));
    }
}
