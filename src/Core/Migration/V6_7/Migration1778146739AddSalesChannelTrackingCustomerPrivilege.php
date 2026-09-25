<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1778146739AddSalesChannelTrackingCustomerPrivilege extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'customer.viewer' => [
            'sales_channel_tracking_customer:read',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1778146739;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }
}
