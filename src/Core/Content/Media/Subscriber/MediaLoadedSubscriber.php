<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Subscriber;

use Shopwell\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\PartialEntityLoadedEvent;
use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
class MediaLoadedSubscriber
{
    /**
     * Accessed via generic entity accessors so it can serve both fully hydrated `MediaEntity`
     * instances (`media.loaded`) and `PartialEntity` instances from partial loading
     * (`media.partial_loaded`), which do not expose the typed media getters/setters.
     *
     * @param EntityLoadedEvent<MediaEntity>|PartialEntityLoadedEvent $event
     */
    public function unserialize(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $media) {
            $mediaTypeRaw = $media->has('mediaTypeRaw') ? $media->get('mediaTypeRaw') : null;

            if ($mediaTypeRaw) {
                /** @phpstan-ignore shopwell.unserializeUsage */
                $media->assign(['mediaType' => \unserialize($mediaTypeRaw)]);
            }

            if (($media->has('thumbnails') ? $media->get('thumbnails') : null) !== null) {
                continue;
            }

            $thumbnailsRo = $media->has('thumbnailsRo') ? $media->get('thumbnailsRo') : null;

            $thumbnails = match (true) {
                /** @phpstan-ignore shopwell.unserializeUsage */
                $thumbnailsRo !== null => \unserialize($thumbnailsRo),
                default => new MediaThumbnailCollection(),
            };

            $media->assign(['thumbnails' => $thumbnails]);
        }
    }
}
