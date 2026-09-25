<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Event\GenericEvent;
use Shopwell\Core\Framework\Event\NestedEvent;
use Shopwell\Core\Framework\Log\Package;

/**
 * @template TEntityCollection of EntityCollection
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class EntitySearchResultLoadedEvent extends NestedEvent implements GenericEvent
{
    protected string $name;

    /**
     * @param EntitySearchResult<TEntityCollection> $result
     */
    public function __construct(
        protected EntityDefinition $definition,
        protected EntitySearchResult $result
    ) {
        $this->name = $this->definition->getEntityName() . '.search.result.loaded';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }

    /**
     * @return EntitySearchResult<TEntityCollection>
     */
    public function getResult(): EntitySearchResult
    {
        return $this->result;
    }
}
