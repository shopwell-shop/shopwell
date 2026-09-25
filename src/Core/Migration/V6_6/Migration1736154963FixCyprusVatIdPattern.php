<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1736154963FixCyprusVatIdPattern extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1736154963;
    }

    public function update(Connection $connection): void
    {
        $connection->update('country', ['vat_id_pattern' => 'CY\d{8}[A-Z]'], ['vat_id_pattern' => 'CY\d{8}L']);
    }
}
