<?php declare(strict_types=1);

namespace Shopwell\Core\System\CustomField\ContentSystem\Mapping;

use Shopwell\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopwell\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationDefinition;
use Shopwell\Core\System\CustomField\CustomFieldDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @internal
 */
#[Package('framework')]
class CustomFieldMappingCandidateCacheInvalidator implements EventSubscriberInterface
{
    private const CACHE_TAG = 'custom_field_mapping_candidates';

    public function __construct(
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [EntityWrittenContainerEvent::class => 'invalidate'];
    }

    public function invalidate(EntityWrittenContainerEvent $event): void
    {
        $metadataChanged = false;

        foreach ([
            CustomFieldDefinition::ENTITY_NAME,
            CustomFieldSetDefinition::ENTITY_NAME,
            CustomFieldSetRelationDefinition::ENTITY_NAME,
        ] as $entityName) {
            if ($event->getEventByEntityName($entityName) !== null) {
                $metadataChanged = true;
                break;
            }
        }

        if (!$metadataChanged) {
            return;
        }

        $this->cache->invalidateTags([self::CACHE_TAG]);

        $this->cacheInvalidator->invalidate([CustomFieldMappingCacheTags::METADATA]);
    }
}
