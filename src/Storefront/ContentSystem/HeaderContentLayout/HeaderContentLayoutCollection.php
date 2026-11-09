<?php declare(strict_types=1);

namespace Shopwell\Storefront\ContentSystem\HeaderContentLayout;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @final
 *
 * @extends EntityCollection<HeaderContentLayoutEntity>
 */
#[Package('framework')]
class HeaderContentLayoutCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'header_content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return HeaderContentLayoutEntity::class;
    }
}
