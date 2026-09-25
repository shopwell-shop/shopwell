<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\Routing;

use Shopwell\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;
use Shopwell\Storefront\Theme\Aggregate\ThemeSalesChannelDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class CachedDomainLoaderInvalidator implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly CacheInvalidator $logger)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => [
                ['invalidate', 2000],
            ],
        ];
    }

    public function invalidate(EntityWrittenContainerEvent $event): void
    {
        if (!$event->getEventByEntityName(SalesChannelDefinition::ENTITY_NAME)
            && !$event->getEventByEntityName(ThemeSalesChannelDefinition::ENTITY_NAME)) {
            return;
        }

        $this->logger->invalidate([
            CachedDomainLoader::CACHE_KEY,
            CachedDomainLoader::DOMAIN_COLLECTION_CACHE_KEY,
        ], true);
    }
}
