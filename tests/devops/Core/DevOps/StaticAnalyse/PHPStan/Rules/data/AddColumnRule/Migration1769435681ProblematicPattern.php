<?php

declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\AddColumnRule;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Migration\MigrationStep;

class Migration1769435681ProblematicPattern extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1769435681;
    }

    public function update(Connection $connection): void
    {
        // This pattern requires COPY algorithm - should be caught
        $connection->executeStatement('
            ALTER TABLE `product`
            ADD COLUMN `states` JSON NULL,
            ADD CONSTRAINT `json.product.states` CHECK (JSON_VALID(`states`))
        ');
    }
}
