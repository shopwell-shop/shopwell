<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Aggregate\FlowSequence;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<FlowSequenceEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class FlowSequenceCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'flow_sequence_collection';
    }

    protected function getExpectedClass(): string
    {
        return FlowSequenceEntity::class;
    }
}
