<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Subscriber;

use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class VideoCoverCleanupSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<MediaCollection> $mediaRepository
     */
    public function __construct(private readonly EntityRepository $mediaRepository)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityDeleteEvent::class => 'removeDanglingCoverReferences',
        ];
    }

    public function removeDanglingCoverReferences(EntityDeleteEvent $event): void
    {
        $deletedIds = array_values($event->getIds(MediaDefinition::ENTITY_NAME));
        if ($deletedIds === []) {
            return;
        }

        // Build a filter to search for videos that might reference the deleted cover IDs
        // We search for videos with metadata containing any of the deleted IDs as coverMediaId
        $coverIdFilters = [];
        foreach ($deletedIds as $deletedId) {
            $coverIdFilters[] = new ContainsFilter('metaData', $deletedId);
        }

        $filters = [
            new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('metaData', null)]),
            new ContainsFilter('mimeType', 'video/'),
            new MultiFilter(MultiFilter::CONNECTION_OR, $coverIdFilters),
        ];

        $criteria = (new Criteria())->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, $filters));

        $mediaWithMetaData = $this->mediaRepository->search($criteria, $event->getContext());

        $updates = [];

        foreach ($mediaWithMetaData->getEntities() as $media) {
            $metaData = $media->getMetaData();
            if ($metaData === null) {
                continue;
            }

            if (!\is_array($metaData['video'] ?? null) || !isset($metaData['video']['coverMediaId'])) {
                continue;
            }

            $coverMediaId = $metaData['video']['coverMediaId'];
            if (!\in_array($coverMediaId, $deletedIds, true)) {
                continue;
            }

            $updatedMetaData = $this->removeCoverFromMetaData($metaData);

            if ($updatedMetaData === $metaData) {
                continue;
            }

            $updates[] = [
                'id' => $media->getId(),
                'metaData' => $updatedMetaData,
            ];
        }

        if ($updates === []) {
            return;
        }

        $event->getContext()->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($updates): void {
            $this->mediaRepository->update($updates, $context);
        });
    }

    /**
     * @param array<string, mixed>|null $metaData
     *
     * @return array<string, mixed>|null
     */
    private function removeCoverFromMetaData(?array $metaData): ?array
    {
        if ($metaData === null) {
            return null;
        }

        if (($metaData['video']['coverMediaId'] ?? null) === null) {
            return $metaData;
        }

        unset($metaData['video']['coverMediaId']);

        if ($metaData['video'] !== []) {
            return $metaData;
        }

        unset($metaData['video']);

        return $metaData === [] ? null : $metaData;
    }
}
