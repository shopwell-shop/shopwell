<?php declare(strict_types=1);

namespace Shopwell\Core\System\Unit;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<UnitEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class UnitCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'unit_collection';
    }

    protected function getExpectedClass(): string
    {
        return UnitEntity::class;
    }
}
