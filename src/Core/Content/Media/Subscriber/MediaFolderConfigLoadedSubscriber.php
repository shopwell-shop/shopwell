<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Subscriber;

use Shopwell\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Shopwell\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class MediaFolderConfigLoadedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'media_folder_configuration.loaded' => ['unserialize', 10],
        ];
    }

    /**
     * @param EntityLoadedEvent<MediaFolderConfigurationEntity> $event
     */
    public function unserialize(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $media) {
            if ($media->getMediaThumbnailSizes() === null) {
                if ($media->getMediaThumbnailSizesRo()) {
                    /** @phpstan-ignore shopwell.unserializeUsage */
                    $media->setMediaThumbnailSizes(\unserialize($media->getMediaThumbnailSizesRo()));
                } else {
                    $media->setMediaThumbnailSizes(new MediaThumbnailSizeCollection());
                }
            }
        }
    }
}
