<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1790667101AddAclRoleIdsToWebhook extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1790667101;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'webhook', 'acl_role_ids', 'JSON');
    }
}
