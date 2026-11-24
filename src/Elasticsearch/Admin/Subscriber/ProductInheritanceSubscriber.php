<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\Admin\Subscriber;

use Shopwell\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopwell\Core\Content\Product\Events\ProductIndexerEvent;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Admin\AdminSearchRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('inventory')]
final readonly class ProductInheritanceSubscriber implements EventSubscriberInterface
{
    public function __construct(private AdminSearchRegistry $registry)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ProductIndexerEvent::class => 'update',
        ];
    }

    public function update(ProductIndexerEvent $event): void
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API') || $event->isFullIndexing || \in_array(ProductIndexer::INHERITANCE_UPDATER, $event->getSkip(), true)) {
            return;
        }

        $this->registry->updateIds(ProductDefinition::ENTITY_NAME, array_values($event->getIds()), $event->getContext());
    }
}
