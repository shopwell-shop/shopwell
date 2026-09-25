<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 *
 * @extends EntityCollection<AttributeEntity>
 */
class AttributeEntityCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AttributeEntity::class;
    }
}
