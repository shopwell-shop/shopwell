<?php declare(strict_types=1);

namespace Shopwell\Storefront\ContentSystem\FooterContentLayout;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @final
 *
 * @extends EntityCollection<FooterContentLayoutEntity>
 */
#[Package('framework')]
class FooterContentLayoutCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'footer_content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return FooterContentLayoutEntity::class;
    }
}
