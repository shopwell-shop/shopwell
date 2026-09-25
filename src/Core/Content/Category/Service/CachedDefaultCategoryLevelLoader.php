<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category\Service;

use Shopwell\Core\Content\Category\CategoryCollection;
use Shopwell\Core\Content\Category\CategoryEvents;
use Shopwell\Core\Content\Category\Event\CategoryLevelLoaderCacheKeyEvent;
use Shopwell\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @internal
 */
#[Package('discovery')]
class CachedDefaultCategoryLevelLoader implements DefaultCategoryLevelLoaderInterface, EventSubscriberInterface
{
    private const CACHE_TAG = 'category_level_loader';

    public function __construct(
        private readonly TagAwareCacheInterface $cache,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DefaultCategoryLevelLoaderInterface $inner,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CategoryEvents::CATEGORY_WRITTEN_EVENT => 'invalidateCache',
            CategoryEvents::CATEGORY_DELETED_EVENT => 'invalidateCache',
        ];
    }

    public function loadLevels(
        string $rootId,
        int $rootLevel,
        SalesChannelContext $context,
        Criteria $criteria,
        int $depth,
    ): CategoryCollection {
        if ($context->getSalesChannel()->getNavigationCategoryId() === $rootId) {
            return $this->cached(
                $rootId,
                $rootLevel,
                $context,
                $criteria,
                $depth,
            );
        }

        return $this->inner->loadLevels($rootId, $rootLevel, $context, $criteria, $depth);
    }

    public function invalidateCache(): void
    {
        $this->cache->invalidateTags([self::CACHE_TAG]);
    }

    private function cached(
        string $rootId,
        int $rootLevel,
        SalesChannelContext $context,
        Criteria $criteria,
        int $depth,
    ): CategoryCollection {
        $cacheKey = $this->getCacheKey($rootId, $context, $criteria, $depth);

        if ($cacheKey === null) {
            return $this->inner->loadLevels($rootId, $rootLevel, $context, $criteria, $depth);
        }

        $fresh = null;

        $compressed = $this->cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($rootId, $rootLevel, $context, $criteria, $depth, &$fresh): string {
                $item->tag(self::CACHE_TAG);

                $fresh = $this->inner->loadLevels($rootId, $rootLevel, $context, $criteria, $depth);

                return CacheValueCompressor::compress($fresh);
            }
        );

        // the levels were built in this call, return them directly instead of
        // uncompressing the cache payload that was just compressed from them
        if ($fresh instanceof CategoryCollection) {
            return $fresh;
        }

        $categories = CacheValueCompressor::uncompress($compressed);
        \assert($categories instanceof CategoryCollection);

        return $categories;
    }

    private function getCacheKey(string $rootId, SalesChannelContext $context, Criteria $criteria, int $depth): ?string
    {
        $event = new CategoryLevelLoaderCacheKeyEvent(
            [
                'rootId' => $rootId,
                'depth' => $depth,
                'salesChannelId' => $context->getSalesChannelId(),
                'languageId' => $context->getContext()->getLanguageId(),
            ],
            $rootId,
            $depth,
            $context,
            $criteria,
        );

        $this->eventDispatcher->dispatch($event);

        if (!$event->shouldCache()) {
            return null;
        }

        return Hasher::hash($event->getParts());
    }
}
