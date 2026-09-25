<?php

declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Migration\MigrationStep;

class Migration1720610754FooBar extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1720610754;
    }

    public function update(Connection $connection): void
    {
        // older migrations should not be considered
        $this->dropColumnIfExists($connection, 'test_table', 'column');
    }
}
