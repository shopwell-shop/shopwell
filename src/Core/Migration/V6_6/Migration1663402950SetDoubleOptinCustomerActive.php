<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1663402950SetDoubleOptinCustomerActive extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1663402950;
    }

    public function update(Connection $connection): void
    {
        $query = <<<'SQL'
            UPDATE
                customer
            SET
                active = 1
            WHERE
                double_opt_in_registration = 1 AND double_opt_in_confirm_date IS NULL AND active = 0;
        SQL;

        $connection->executeStatement($query);
    }
}
