<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1794889181WorkflowRoleSelection extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794889181;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, ['workflow.viewer' => ['acl_role:read']]);
    }
}
