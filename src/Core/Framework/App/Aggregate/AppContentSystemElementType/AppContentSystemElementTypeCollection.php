<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\AppContentSystemElementType;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<AppContentSystemElementTypeEntity>
 */
#[Package('framework')]
class AppContentSystemElementTypeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppContentSystemElementTypeEntity::class;
    }
}
