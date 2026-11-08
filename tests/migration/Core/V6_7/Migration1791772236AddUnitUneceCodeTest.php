<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_7\Migration1791772236AddUnitUneceCode;
use Shopwell\Core\System\Unit\UnitDefinition;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1791772236AddUnitUneceCode::class)]
class Migration1791772236AddUnitUneceCodeTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1791772236, (new Migration1791772236AddUnitUneceCode())->getCreationTimestamp());
    }

    public function testMigrationAddsNullableUneceCodeColumnAndIsIdempotent(): void
    {
        if (TableHelper::columnExists($this->connection, 'unit', 'unece_code')) {
            $this->connection->executeStatement('ALTER TABLE `unit` DROP COLUMN `unece_code`');
        }

        $migration = new Migration1791772236AddUnitUneceCode();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $column = $this->connection
            ->createSchemaManager()
            ->introspectTableByUnquotedName(UnitDefinition::ENTITY_NAME)
            ->getColumn('unece_code');

        static::assertFalse($column->getNotnull());
        static::assertSame(3, $column->getLength());
    }
}
