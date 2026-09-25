<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopwell\Core\Framework\Event\GenericEvent;
use Shopwell\Core\Framework\Event\NestedEvent;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class EntityAggregationResultLoadedEvent extends NestedEvent implements GenericEvent
{
    protected string $name;

    public function __construct(
        protected EntityDefinition $definition,
        protected AggregationResultCollection $result,
        protected Context $context
    ) {
        $this->name = $this->definition->getEntityName() . '.aggregation.result.loaded';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getResult(): AggregationResultCollection
    {
        return $this->result;
    }
}
