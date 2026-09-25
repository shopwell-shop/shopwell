<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_7\Migration1772626151AppMcpTool;
use Shopwell\Core\Migration\V6_7\Migration1777020096AppMcpToolRequiredPrivileges;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1777020096AppMcpToolRequiredPrivileges::class)]
class Migration1777020096AppMcpToolRequiredPrivilegesTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_mcp_tool_translation`;');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_mcp_tool`;');

        (new Migration1772626151AppMcpTool())->update($this->connection);
    }

    public function testMigration(): void
    {
        $migration = new Migration1777020096AppMcpToolRequiredPrivileges();
        static::assertSame(1777020096, $migration->getCreationTimestamp());

        static::assertFalse(TableHelper::columnExists($this->connection, 'app_mcp_tool', 'required_privileges'));

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'app_mcp_tool', 'required_privileges'));
    }
}
