<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1673966228UpdateVersionAndOrderLineItemPrivilegeForOrderRoles extends MigrationStep
{
    final public const PRIVILEGES = [
        'order.viewer' => [
            'order:delete',
            'version:delete',
        ],
        'order.editor' => [
            'order_line_item:delete',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1673966228;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::PRIVILEGES);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
