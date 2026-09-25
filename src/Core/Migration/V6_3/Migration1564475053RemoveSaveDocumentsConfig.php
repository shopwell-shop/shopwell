<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1564475053RemoveSaveDocumentsConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1564475053;
    }

    public function update(Connection $connection): void
    {
        $connection->delete('system_config', [
            'configuration_key' => 'core.saveDocuments',
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
