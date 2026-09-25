<?php declare(strict_types=1);

namespace Shopwell\Core\System\Tag;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<TagEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class TagCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'tag_collection';
    }

    protected function getExpectedClass(): string
    {
        return TagEntity::class;
    }
}
