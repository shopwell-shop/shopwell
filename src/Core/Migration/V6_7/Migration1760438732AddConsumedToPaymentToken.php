<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1760438732AddConsumedToPaymentToken extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1760438732;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'payment_token', 'consumed')) {
            $this->addColumn($connection, 'payment_token', 'consumed', 'tinyint(1)', default: '0');
        }
    }
}
