<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopwell\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1697112044PaymentAndShippingTechnicalNameRequired extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1697112044;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, PaymentMethodDefinition::ENTITY_NAME, 'technical_name')
            && !TableHelper::getColumnOfTable($connection, PaymentMethodDefinition::ENTITY_NAME, 'technical_name')->isNotNull
        ) {
            $connection->executeStatement('ALTER TABLE `payment_method` MODIFY COLUMN `technical_name` VARCHAR(255) NOT NULL');
        }

        if (TableHelper::columnExists($connection, ShippingMethodDefinition::ENTITY_NAME, 'technical_name')
            && !TableHelper::getColumnOfTable($connection, ShippingMethodDefinition::ENTITY_NAME, 'technical_name')->isNotNull
        ) {
            $connection->executeStatement('ALTER TABLE `shipping_method` MODIFY COLUMN `technical_name` VARCHAR(255) NOT NULL');
        }
    }
}
