<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\NestedEvent;
use Shopwell\Core\Framework\Event\NestedEventCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class EntityLoadedContainerEvent extends NestedEvent
{
    /**
     * @param list<NestedEvent> $events
     */
    public function __construct(
        private readonly Context $context,
        private readonly array $events
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection($this->events);
    }
}
