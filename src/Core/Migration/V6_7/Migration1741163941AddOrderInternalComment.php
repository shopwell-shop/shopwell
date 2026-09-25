<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1741163941AddOrderInternalComment extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1741163941;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'order', 'internal_comment', 'longtext');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
