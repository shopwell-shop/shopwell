<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1787311123AddSourceTypeToStateMachineHistory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1787311123;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, StateMachineHistoryDefinition::ENTITY_NAME, 'source_type', 'VARCHAR(32)');
    }
}
