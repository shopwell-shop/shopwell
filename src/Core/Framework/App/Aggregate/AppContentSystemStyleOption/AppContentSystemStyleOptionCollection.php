<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\AppContentSystemStyleOption;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<AppContentSystemStyleOptionEntity>
 */
#[Package('framework')]
class AppContentSystemStyleOptionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppContentSystemStyleOptionEntity::class;
    }
}
