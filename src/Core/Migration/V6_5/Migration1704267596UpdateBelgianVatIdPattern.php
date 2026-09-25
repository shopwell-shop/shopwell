<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1704267596UpdateBelgianVatIdPattern extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1704267596;
    }

    public function update(Connection $connection): void
    {
        $connection->update('country', ['vat_id_pattern' => '(BE)?(0|1)[0-9]{9}'], ['vat_id_pattern' => '(BE)?0[0-9]{9}']);
    }
}
