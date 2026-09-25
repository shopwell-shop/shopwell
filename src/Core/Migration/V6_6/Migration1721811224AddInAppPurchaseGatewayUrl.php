<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1721811224AddInAppPurchaseGatewayUrl extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1721811224;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'app', 'in_app_purchases_gateway_url', 'VARCHAR(255)');
    }
}
