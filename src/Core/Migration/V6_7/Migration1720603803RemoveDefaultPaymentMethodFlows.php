<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1720603803RemoveDefaultPaymentMethodFlows extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1720603803;
    }

    public function update(Connection $connection): void
    {
        $connection->update(
            'flow',
            [
                'invalid' => 1,
                'active' => 0,
            ],
            ['event_name' => 'checkout.customer.changed-payment-method']
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->delete('flow', ['event_name' => 'checkout.customer.changed-payment-method']);
    }
}
