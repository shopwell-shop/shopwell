<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1625816310AddDefaultToCartRuleIds extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1625816310;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE cart SET rule_ids = "[]" WHERE rule_ids = "" OR rule_ids IS NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
