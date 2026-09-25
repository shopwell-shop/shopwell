<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1646397836UpdateRolePrivilegesOfOrderCreator extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'order.creator' => [
            'api_proxy_switch-customer',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1646397836;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
