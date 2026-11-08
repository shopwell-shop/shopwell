<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\AppContentSystemBindingSpecification;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<AppContentSystemBindingSpecificationEntity>
 */
#[Package('framework')]
class AppContentSystemBindingSpecificationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppContentSystemBindingSpecificationEntity::class;
    }
}
