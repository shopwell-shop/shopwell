<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\Product;

use Shopwell\Core\Content\Product\Events\ProductIndexerEvent;
use Shopwell\Core\Content\Product\Events\ProductStockAlteredEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class ProductUpdater implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ElasticsearchIndexer $indexer,
        private readonly EntityDefinition $definition
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductIndexerEvent::class => 'update',
            ProductStockAlteredEvent::class => 'update',
        ];
    }

    public function update(ProductIndexerEvent|ProductStockAlteredEvent $event): void
    {
        $this->indexer->updateIds($this->definition, $event->getIds());
    }
}
