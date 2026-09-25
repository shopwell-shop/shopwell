<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\Framework\Subscriber;

use Shopwell\Core\Framework\Api\Event\InvalidateExpiredCacheRequestEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Framework\Indexing\IndexManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class InvalidateExpiredCacheSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly IndexManager $indexManager
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InvalidateExpiredCacheRequestEvent::class => 'refreshOpensearchIndices',
        ];
    }

    public function refreshOpensearchIndices(InvalidateExpiredCacheRequestEvent $event): void
    {
        if ($event->request->query->getBoolean('refreshOpenSearch')) {
            $this->indexManager->refreshIndices();
        }
    }
}
