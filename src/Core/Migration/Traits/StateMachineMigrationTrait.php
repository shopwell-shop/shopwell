<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\Traits;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
trait StateMachineMigrationTrait
{
    private function import(StateMachineMigration $migration, Connection $connection): StateMachineMigration
    {
        return (new StateMachineMigrationImporter($connection))->importStateMachine($migration);
    }
}
