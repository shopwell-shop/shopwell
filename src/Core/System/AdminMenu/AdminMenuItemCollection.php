<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<AdminMenuItemEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class AdminMenuItemCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'admin_menu_item_collection';
    }

    protected function getExpectedClass(): string
    {
        return AdminMenuItemEntity::class;
    }
}
