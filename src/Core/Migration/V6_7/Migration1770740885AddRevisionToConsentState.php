<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('data-services')]
class Migration1770740885AddRevisionToConsentState extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1770740885;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, 'consent_state', 'revision')) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `consent_state`
            ADD COLUMN `revision` VARCHAR(255) NULL
        ');
    }
}
