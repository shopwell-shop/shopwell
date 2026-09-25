<?php declare(strict_types=1);

namespace Shopwell\Core\System\NumberRange;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<NumberRangeEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class NumberRangeCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'number_range_collection';
    }

    protected function getExpectedClass(): string
    {
        return NumberRangeEntity::class;
    }
}
