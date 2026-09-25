<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1674704527UpdateVATPatternForCyprusCountry extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1674704527;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE country SET vat_id_pattern = :pattern WHERE iso = :iso;',
            ['pattern' => '(CY)?[0-9]{8}[A-Z]{1}', 'iso' => 'CY']
        );
    }
}
