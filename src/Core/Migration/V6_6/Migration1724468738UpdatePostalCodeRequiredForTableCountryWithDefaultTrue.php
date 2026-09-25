<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1724468738UpdatePostalCodeRequiredForTableCountryWithDefaultTrue extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1724468738;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE country SET postal_code_required = 1 WHERE postal_code_required = 0 AND updated_at IS NULL');
    }
}
