<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1718658881AddValidationDataToOrderTransaction extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1718658881;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'order_transaction', 'validation_data', 'JSON NULL');
    }
}
