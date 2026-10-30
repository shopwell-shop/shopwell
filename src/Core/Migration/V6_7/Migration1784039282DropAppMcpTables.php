<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1784039282DropAppMcpTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1784039282;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        // app MCP tools/prompts/resources moved to the generic app_feature table
        $connection->executeStatement('DROP TABLE IF EXISTS `app_mcp_tool_translation`');
        $connection->executeStatement('DROP TABLE IF EXISTS `app_mcp_prompt_translation`');
        $connection->executeStatement('DROP TABLE IF EXISTS `app_mcp_resource_translation`');
        $connection->executeStatement('DROP TABLE IF EXISTS `app_mcp_tool`');
        $connection->executeStatement('DROP TABLE IF EXISTS `app_mcp_prompt`');
        $connection->executeStatement('DROP TABLE IF EXISTS `app_mcp_resource`');
    }
}
