<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1653376989ResetDefaultAlwaysValidConditionValue extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1653376989;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE `rule_condition` SET `value` = null WHERE `type` = \'alwaysValid\' AND `value` LIKE \'{"isAlwaysValid": true}\';');

        $this->registerIndexer($connection, 'Swag.RulePayloadIndexer');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
