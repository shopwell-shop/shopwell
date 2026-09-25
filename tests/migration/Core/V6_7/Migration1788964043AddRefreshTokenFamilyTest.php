<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_7\Migration1788964043AddRefreshTokenFamily;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1788964043AddRefreshTokenFamily::class)]
class Migration1788964043AddRefreshTokenFamilyTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1788964043, (new Migration1788964043AddRefreshTokenFamily())->getCreationTimestamp());
    }

    public function testMigrationAddsFamilyStateAndIsIdempotent(): void
    {
        $migration = new Migration1788964043AddRefreshTokenFamily();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'refresh_token', 'family_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'refresh_token', 'revoked_at'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'refresh_token', 'idx.refresh_token.family_id'));
    }
}
