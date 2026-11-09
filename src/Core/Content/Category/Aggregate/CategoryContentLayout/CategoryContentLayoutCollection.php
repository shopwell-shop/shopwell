<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category\Aggregate\CategoryContentLayout;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 *
 * @extends EntityCollection<CategoryContentLayoutEntity>
 */
#[Package('discovery')]
class CategoryContentLayoutCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'category_content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return CategoryContentLayoutEntity::class;
    }
}
