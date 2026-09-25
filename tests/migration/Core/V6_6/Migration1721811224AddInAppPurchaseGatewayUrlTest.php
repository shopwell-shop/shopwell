<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_6\Migration1721811224AddInAppPurchaseGatewayUrl;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1721811224AddInAppPurchaseGatewayUrl::class)]
class Migration1721811224AddInAppPurchaseGatewayUrlTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1721811224, (new Migration1721811224AddInAppPurchaseGatewayUrl())->getCreationTimestamp());
    }

    public function testMigrate(): void
    {
        $this->rollback();
        $this->migrate();
        $this->migrate();

        $column = TableHelper::getColumnOfTable($this->connection, 'app', 'in_app_purchases_gateway_url');
        static::assertFalse($column->isNotNull);
    }

    private function migrate(): void
    {
        (new Migration1721811224AddInAppPurchaseGatewayUrl())->update($this->connection);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('ALTER TABLE `app` DROP COLUMN `in_app_purchases_gateway_url`');
    }
}
