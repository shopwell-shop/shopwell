<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\DevOps\Test\Command\Fixture;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1763996000Dummy extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1763996000;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
