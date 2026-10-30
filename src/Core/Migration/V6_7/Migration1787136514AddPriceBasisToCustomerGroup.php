<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1787136514AddPriceBasisToCustomerGroup extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1787136514;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, CustomerGroupDefinition::ENTITY_NAME, 'price_basis', 'VARCHAR(255)');

        $connection->executeStatement('UPDATE `customer_group` SET `price_basis` = IF(`display_gross` = 0, \'net\', \'gross\') WHERE `price_basis` IS NULL');

        $this->executeDdlStatement($connection, 'ALTER TABLE `customer_group` MODIFY COLUMN `price_basis` VARCHAR(255) NOT NULL DEFAULT \'gross\'');
    }
}
