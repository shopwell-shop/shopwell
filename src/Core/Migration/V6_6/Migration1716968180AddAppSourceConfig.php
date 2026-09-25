<?php

declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1716968180AddAppSourceConfig extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1716968180;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'app', 'source_config', 'JSON', false, '(JSON_OBJECT())');
    }
}
