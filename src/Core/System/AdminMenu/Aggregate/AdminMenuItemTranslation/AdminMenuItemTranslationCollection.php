<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu\Aggregate\AdminMenuItemTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<AdminMenuItemTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class AdminMenuItemTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'admin_menu_item_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return AdminMenuItemTranslationEntity::class;
    }
}
