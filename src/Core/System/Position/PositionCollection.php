<?php declare(strict_types=1);

namespace Shopwell\Core\System\Position;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PositionEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class PositionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'position_collection';
    }

    protected function getExpectedClass(): string
    {
        return PositionEntity::class;
    }
}
