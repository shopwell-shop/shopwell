<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_7\Migration1756305375AddCategoriesIndexToProduct;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1756305375AddCategoriesIndexToProduct::class)]
class Migration1756305375AddCategoriesIndexToProductTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1756305375, (new Migration1756305375AddCategoriesIndexToProduct())->getCreationTimestamp());
    }

    public function testIndexIsCreated(): void
    {
        if (TableHelper::indexExists($this->connection, 'product', 'idx.product.categories')) {
            $this->connection->executeStatement('DROP INDEX `idx.product.categories` ON `product`');
        }

        $migration = new Migration1756305375AddCategoriesIndexToProduct();
        $migration->update($this->connection);

        static::assertTrue(TableHelper::indexExists($this->connection, 'product', 'idx.product.categories'));
    }

    public function testMigrationIsIdempotent(): void
    {
        $migration = new Migration1756305375AddCategoriesIndexToProduct();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::indexExists($this->connection, 'product', 'idx.product.categories'));
    }
}
