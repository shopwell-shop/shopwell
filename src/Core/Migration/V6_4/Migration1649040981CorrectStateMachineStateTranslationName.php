<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1649040981CorrectStateMachineStateTranslationName extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1649040981;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE state_machine_state_translation SET name = :expectName WHERE name = :actualName',
            ['expectName' => 'In Progress', 'actualName' => 'In progress']
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
