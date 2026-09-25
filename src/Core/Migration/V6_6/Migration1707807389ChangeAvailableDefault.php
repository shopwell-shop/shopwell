<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1707807389ChangeAvailableDefault extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1707807389;
    }

    public function update(Connection $connection): void
    {
        $this->executeDdlStatement($connection, 'ALTER TABLE `product` CHANGE `available` `available` tinyint(1) NOT NULL DEFAULT \'0\';');
    }
}
