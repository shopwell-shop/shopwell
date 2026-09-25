<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_6\Migration1735807464AddCustomFieldStoreApiAware;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1735807464AddCustomFieldStoreApiAware::class)]
class Migration1735807464AddCustomFieldStoreApiAwareTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1735807464, (new Migration1735807464AddCustomFieldStoreApiAware())->getCreationTimestamp());
    }

    public function testCustomFieldHasStoreApiAwareColumn(): void
    {
        $this->rollback();
        $this->executeMigration();
        $this->executeMigration();

        $storeApiAwareColumn = TableHelper::getColumnOfTable($this->connection, 'custom_field', 'store_api_aware');
        static::assertSame(Types::BOOLEAN, $storeApiAwareColumn->type);
        static::assertTrue($storeApiAwareColumn->isNotNull);
        static::assertSame('1', $storeApiAwareColumn->defaultValue);
    }

    private function executeMigration(): void
    {
        (new Migration1735807464AddCustomFieldStoreApiAware())->update($this->connection);
    }

    private function rollback(): void
    {
        if (!TableHelper::columnExists($this->connection, 'custom_field', 'store_api_aware')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `custom_field` DROP COLUMN `store_api_aware`');
    }
}
