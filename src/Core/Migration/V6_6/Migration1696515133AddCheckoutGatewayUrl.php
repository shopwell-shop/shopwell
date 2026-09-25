<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1696515133AddCheckoutGatewayUrl extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1696515133;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'app', 'checkout_gateway_url', 'VARCHAR(255) NULL');
    }
}
