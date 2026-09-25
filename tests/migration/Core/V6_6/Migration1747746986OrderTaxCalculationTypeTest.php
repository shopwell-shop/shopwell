<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_6\Migration1747746986OrderTaxCalculationType;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1747746986OrderTaxCalculationType::class)]
class Migration1747746986OrderTaxCalculationTypeTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1747746986, (new Migration1747746986OrderTaxCalculationType())->getCreationTimestamp());
    }

    public function testAddedColumn(): void
    {
        if (TableHelper::columnExists($this->connection, 'order', 'tax_calculation_type')) {
            $this->rollback();
        }

        $migration = new Migration1747746986OrderTaxCalculationType();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'order', 'tax_calculation_type'));
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('ALTER TABLE `order` DROP COLUMN `tax_calculation_type`');
    }
}
