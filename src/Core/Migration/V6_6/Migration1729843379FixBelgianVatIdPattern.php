<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1729843379FixBelgianVatIdPattern extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1729843379;
    }

    public function update(Connection $connection): void
    {
        $connection->update('country', ['vat_id_pattern' => 'BE(0|1)\d{9}'], ['vat_id_pattern' => 'BE0\d{9}']);
    }
}
