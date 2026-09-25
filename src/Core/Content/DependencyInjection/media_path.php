<?php declare(strict_types=1);

namespace Shopwell\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopwell\Core\Content\Media\Core\Application\AbstractMediaUrlGenerator;
use Shopwell\Core\Content\Media\Core\Application\MediaLocationBuilder;
use Shopwell\Core\Content\Media\Core\Application\MediaPathStorage;
use Shopwell\Core\Content\Media\Core\Application\MediaPathUpdater;
use Shopwell\Core\Content\Media\Core\Application\MediaUrlLoader;
use Shopwell\Core\Content\Media\Core\Application\RemoteThumbnailLoader;
use Shopwell\Core\Content\Media\Core\Event\UpdateMediaPathEvent;
use Shopwell\Core\Content\Media\Core\Event\UpdateThumbnailPathEvent;
use Shopwell\Core\Content\Media\Core\Strategy\FilenamePathStrategy;
use Shopwell\Core\Content\Media\Core\Strategy\IdPathStrategy;
use Shopwell\Core\Content\Media\Core\Strategy\PathStrategyFactory;
use Shopwell\Core\Content\Media\Core\Strategy\PhysicalFilenamePathStrategy;
use Shopwell\Core\Content\Media\Core\Strategy\PlainPathStrategy;
use Shopwell\Core\Content\Media\Event\MediaPathChangedEvent;
use Shopwell\Core\Content\Media\Infrastructure\Command\UpdatePathCommand;
use Shopwell\Core\Content\Media\Infrastructure\Path\BanMediaUrl;
use Shopwell\Core\Content\Media\Infrastructure\Path\FastlyMediaReverseProxy;
use Shopwell\Core\Content\Media\Infrastructure\Path\MediaPathPostUpdater;
use Shopwell\Core\Content\Media\Infrastructure\Path\MediaUrlGenerator;
use Shopwell\Core\Content\Media\Infrastructure\Path\SqlMediaLocationBuilder;
use Shopwell\Core\Content\Media\Infrastructure\Path\SqlMediaPathStorage;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MediaUrlLoader::class)
        ->args([
            service(AbstractMediaUrlGenerator::class),
            service(RemoteThumbnailLoader::class),
            param('shopwell.media.remote_thumbnails.enable'),
        ])
        ->tag('kernel.event_listener', ['event' => 'media.loaded', 'method' => 'loaded', 'priority' => 20])
        ->tag('kernel.event_listener', ['event' => 'media.partial_loaded', 'method' => 'loaded', 'priority' => 19]);

    $services->set(RemoteThumbnailLoader::class)
        ->args([
            service(AbstractMediaUrlGenerator::class),
            service(Connection::class),
            service('shopwell.filesystem.public'),
            service(ExtensionDispatcher::class),
            param('shopwell.media.remote_thumbnails.pattern'),
            param('shopwell.media.remote_thumbnails.fallback_sizes'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(MediaLocationBuilder::class, SqlMediaLocationBuilder::class)
        ->args([
            service('event_dispatcher'),
            service(Connection::class),
        ]);

    $services->set(MediaPathUpdater::class)
        ->args([
            service(AbstractMediaPathStrategy::class),
            service(MediaLocationBuilder::class),
            service(MediaPathStorage::class),
        ])
        ->tag('kernel.event_listener', ['event' => UpdateMediaPathEvent::class, 'method' => 'updateMedia', 'priority' => 0])
        ->tag('kernel.event_listener', ['event' => UpdateThumbnailPathEvent::class, 'method' => 'updateThumbnails', 'priority' => 0]);

    $services->set(MediaPathStorage::class, SqlMediaPathStorage::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(PathStrategyFactory::class)
        ->args([
            tagged_iterator('shopwell.path.strategy'),
        ]);

    $services->set(FilenamePathStrategy::class)
        ->args([
            '$pathCacheBuster' => param('shopwell.cdn.path_cache_buster'),
        ])
        ->tag('shopwell.path.strategy');

    $services->set(IdPathStrategy::class)
        ->args([
            '$pathCacheBuster' => param('shopwell.cdn.path_cache_buster'),
        ])
        ->tag('shopwell.path.strategy');

    $services->set(PhysicalFilenamePathStrategy::class)
        ->args([
            '$pathCacheBuster' => param('shopwell.cdn.path_cache_buster'),
        ])
        ->tag('shopwell.path.strategy');

    $services->set(PlainPathStrategy::class)
        ->args([
            '$pathCacheBuster' => param('shopwell.cdn.path_cache_buster'),
        ])
        ->tag('shopwell.path.strategy');

    $services->set(AbstractMediaUrlGenerator::class, MediaUrlGenerator::class)
        ->args([
            service('shopwell.filesystem.public'),
        ]);

    $services->set(AbstractMediaPathStrategy::class)
        ->factory([service(PathStrategyFactory::class), 'factory'])
        ->args([
            param('shopwell.cdn.strategy'),
        ]);

    $services->set(MediaPathPostUpdater::class)
        ->args([
            service(IteratorFactory::class),
            service(MediaPathUpdater::class),
            service(Connection::class),
            service(EntityIndexerRegistry::class),
        ])
        ->tag('shopwell.entity_indexer');

    $services->set(UpdatePathCommand::class)
        ->tag('console.command')
        ->args([
            service(MediaPathUpdater::class),
            service(Connection::class),
        ]);

    $services->set(BanMediaUrl::class)
        ->args([
            service('shopwell.media.reverse_proxy'),
            service(AbstractMediaUrlGenerator::class),
        ])
        ->tag('kernel.event_listener', ['event' => MediaPathChangedEvent::class, 'method' => 'changed']);

    $services->alias('shopwell.media.reverse_proxy', FastlyMediaReverseProxy::class);

    $services->set(FastlyMediaReverseProxy::class)
        ->args([
            service('shopwell.reverse_proxy.http_client'),
            param('shopwell.cdn.fastly.api_key'),
            param('shopwell.cdn.fastly.soft_purge'),
            param('shopwell.cdn.fastly.max_parallel_invalidations'),
            service('logger'),
        ]);
};
