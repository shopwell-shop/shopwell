<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\Admin\Subscriber;

use Shopwell\Core\Framework\DataAbstractionLayer\Event\RefreshIndexEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Admin\AdminIndexingBehavior;
use Shopwell\Elasticsearch\Admin\AdminSearchRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('inventory')]
final readonly class RefreshIndexSubscriber implements EventSubscriberInterface
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
            RefreshIndexEvent::class => 'handled',
        ];
    }

    public function handled(RefreshIndexEvent $event): void
    {
        $this->registry->iterate(
            new AdminIndexingBehavior(
                $event->getNoQueue(),
                $event->getSkipEntities(),
                $event->getOnlyEntities()
            )
        );
    }
}
