<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\InheritanceUpdaterTrait;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1610523204AddInheritanceForProductCmsPage extends MigrationStep
{
    use InheritanceUpdaterTrait;

    public function getCreationTimestamp(): int
    {
        return 1610523204;
    }

    public function update(Connection $connection): void
    {
        $this->updateInheritance($connection, 'product', 'cmsPage');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
