<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1794890800WorkflowDepartmentSelection extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794890800;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, [
            'workflow.viewer' => ['organization:read', 'organization_unit:read'],
            'workflow_participant.viewer' => ['organization:read', 'organization_unit:read'],
        ]);
    }
}
