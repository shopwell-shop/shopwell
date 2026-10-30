<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_7\Migration1772626151AppMcpTool;
use Shopwell\Core\Migration\V6_7\Migration1773409342AppMcpPrompt;
use Shopwell\Core\Migration\V6_7\Migration1773409343AppMcpResource;
use Shopwell\Core\Migration\V6_7\Migration1777020096AppMcpToolRequiredPrivileges;
use Shopwell\Core\Migration\V6_7\Migration1784039282DropAppMcpTables;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1784039282DropAppMcpTables::class)]
class Migration1784039282DropAppMcpTablesTest extends TestCase
{
    use KernelTestBehaviour;

    private const TABLES = [
        'app_mcp_tool_translation',
        'app_mcp_prompt_translation',
        'app_mcp_resource_translation',
        'app_mcp_tool',
        'app_mcp_prompt',
        'app_mcp_resource',
    ];

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1784039282, (new Migration1784039282DropAppMcpTables())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->createTables();

        $migration = new Migration1784039282DropAppMcpTables();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        foreach (self::TABLES as $table) {
            static::assertFalse(TableHelper::tableExists($this->connection, $table));
        }
    }

    private function createTables(): void
    {
        (new Migration1772626151AppMcpTool())->update($this->connection);
        (new Migration1773409342AppMcpPrompt())->update($this->connection);
        (new Migration1773409343AppMcpResource())->update($this->connection);
        (new Migration1777020096AppMcpToolRequiredPrivileges())->update($this->connection);
    }
}
