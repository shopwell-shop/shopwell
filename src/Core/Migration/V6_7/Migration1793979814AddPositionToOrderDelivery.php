<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1793979814AddPositionToOrderDelivery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793979814;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'order_delivery', 'position', 'INT', false, '0');
    }
}
