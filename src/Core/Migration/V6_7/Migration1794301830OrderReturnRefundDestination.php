<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1794301830OrderReturnRefundDestination extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794301830;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'order_return', 'refund_destination', 'VARCHAR(16)');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
