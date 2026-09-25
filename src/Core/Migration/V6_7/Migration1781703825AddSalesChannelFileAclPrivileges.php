<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1781703825AddSalesChannelFileAclPrivileges extends MigrationStep
{
    final public const NEW_PRIVILEGES = [
        'sales_channel.viewer' => [
            'sales_channel_file:read',
        ],
        'sales_channel.editor' => [
            'sales_channel_file:create',
            'sales_channel_file:update',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1781703825;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, self::NEW_PRIVILEGES);
    }
}
