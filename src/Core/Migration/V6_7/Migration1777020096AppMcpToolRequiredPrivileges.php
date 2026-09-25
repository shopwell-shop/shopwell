<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\AddColumnTrait;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1777020096AppMcpToolRequiredPrivileges extends MigrationStep
{
    use AddColumnTrait;

    public function getCreationTimestamp(): int
    {
        return 1777020096;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'app_mcp_tool', 'required_privileges', 'JSON');
    }
}
