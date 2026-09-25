<?php declare(strict_types=1);

namespace Shopwell\Storefront\Theme\Subscriber;

use Shopwell\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Storefront\Theme\ThemeCollection;
use Shopwell\Storefront\Theme\ThemeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class UnusedMediaSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<ThemeCollection> $themeRepository
     */
    public function __construct(
        private readonly EntityRepository $themeRepository,
        private readonly ThemeService $themeService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UnusedMediaSearchEvent::class => 'removeUsedMedia',
        ];
    }

    public function removeUsedMedia(UnusedMediaSearchEvent $event): void
    {
        $context = Context::createDefaultContext();
        $allThemeIds = $this->themeRepository->searchIds(new Criteria(), $context)->getIds();

        $mediaIds = [];
        foreach ($allThemeIds as $themeId) {
            if (!Feature::isActive('v6.8.0.0')) {
                $config = Feature::silent('v6.8.0.0', function () use ($themeId, $context) {
                    return $this->themeService->getThemeConfiguration($themeId, false, $context);
                });
            } else {
                $config = $this->themeService->getPlainThemeConfiguration($themeId, $context);
            }

            foreach ($config['fields'] ?? [] as $data) {
                if ($data['type'] === 'media' && $data['value'] && Uuid::isValid($data['value'])) {
                    $mediaIds[] = $data['value'];
                }
            }
        }

        $event->markAsUsed(array_unique($mediaIds));
    }
}
