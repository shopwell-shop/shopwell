<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1713345551AddAppManagedColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1713345551;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'app', 'self_managed', 'TINYINT(1)', false, '0');
    }
}
