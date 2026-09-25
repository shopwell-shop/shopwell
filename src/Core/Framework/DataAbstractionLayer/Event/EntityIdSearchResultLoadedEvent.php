<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopwell\Core\Framework\Event\GenericEvent;
use Shopwell\Core\Framework\Event\NestedEvent;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class EntityIdSearchResultLoadedEvent extends NestedEvent implements GenericEvent
{
    protected string $name;

    public function __construct(
        protected EntityDefinition $definition,
        protected IdSearchResult $result
    ) {
        $this->name = $this->definition->getEntityName() . '.id.search.result.loaded';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }

    public function getResult(): IdSearchResult
    {
        return $this->result;
    }
}
