<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_7\Migration1756068710FixCustomerAddressLastNameLength;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1756068710FixCustomerAddressLastNameLength::class)]
class Migration1756068710FixCustomerAddressLastNameLengthTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1756068710FixCustomerAddressLastNameLength();
        static::assertSame(1756068710, $migration->getCreationTimestamp());
    }

    public function testMigrationChangesColumnLengthAndIsIdempotent(): void
    {
        $migration = new Migration1756068710FixCustomerAddressLastNameLength();

        // Set column to original size to test the migration properly, as test DB may already have VARCHAR(255)
        $this->connection->executeStatement('
            ALTER TABLE `customer_address`
            MODIFY COLUMN `name` VARCHAR(60) COLLATE utf8mb4_unicode_ci NOT NULL
        ');

        $nameColumn = TableHelper::getColumnOfTable($this->connection, 'customer_address', 'name');
        static::assertSame(Types::STRING, $nameColumn->type);
        static::assertSame(60, $nameColumn->length);

        $migration->update($this->connection);

        $nameColumn = TableHelper::getColumnOfTable($this->connection, 'customer_address', 'name');
        static::assertSame(Types::STRING, $nameColumn->type);
        static::assertSame(255, $nameColumn->length);
        static::assertTrue($nameColumn->isNotNull);

        $migration->update($this->connection);

        $nameColumn = TableHelper::getColumnOfTable($this->connection, 'customer_address', 'name');
        static::assertSame(Types::STRING, $nameColumn->type);
        static::assertSame(255, $nameColumn->length);
        static::assertTrue($nameColumn->isNotNull);
    }
}
