<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Events;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched with the ids of products whose `available` flag changed from true to false.
 * Products which became available are dispatched with the `ProductBackInStockEvent`.
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductNoLongerAvailableEvent extends Event implements ShopwellEvent, ProductChangedEventInterface
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        protected array $ids,
        protected Context $context
    ) {
    }

    /**
     * @return list<string>
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
