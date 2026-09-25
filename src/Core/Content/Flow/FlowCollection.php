<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<FlowEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class FlowCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'flow_collection';
    }

    protected function getExpectedClass(): string
    {
        return FlowEntity::class;
    }
}
