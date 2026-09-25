<?php declare(strict_types=1);

namespace Shopwell\Core\System\DeliveryTime;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DeliveryTimeEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class DeliveryTimeCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'delivery_time_collection';
    }

    protected function getExpectedClass(): string
    {
        return DeliveryTimeEntity::class;
    }
}
