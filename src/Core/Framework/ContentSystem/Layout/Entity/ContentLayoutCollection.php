<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Layout\Entity;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @final
 *
 * @extends EntityCollection<ContentLayoutEntity>
 */
#[Package('framework')]
class ContentLayoutCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return ContentLayoutEntity::class;
    }
}
