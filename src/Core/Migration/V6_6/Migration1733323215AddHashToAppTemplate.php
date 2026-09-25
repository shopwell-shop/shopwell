<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1733323215AddHashToAppTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1733323215;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'app_template',
            'hash',
            'VARCHAR(32)'
        );
    }
}
