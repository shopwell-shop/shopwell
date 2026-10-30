<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1792408054RemovePromotionOrdersPerCustomerCountColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792408054;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'promotion', 'orders_per_customer_count');
    }
}
