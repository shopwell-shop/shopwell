<?php declare(strict_types=1);

namespace Shopwell\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Cart\CartRuleLoader;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopwell\Core\Content\Sitemap\Commands\SitemapGenerateCommand;
use Shopwell\Core\Content\Sitemap\ConfigHandler\File;
use Shopwell\Core\Content\Sitemap\Provider\CategoryUrlProvider;
use Shopwell\Core\Content\Sitemap\Provider\CustomUrlProvider;
use Shopwell\Core\Content\Sitemap\Provider\HomeUrlProvider;
use Shopwell\Core\Content\Sitemap\Provider\LandingPageUrlProvider;
use Shopwell\Core\Content\Sitemap\Provider\ProductUrlProvider;
use Shopwell\Core\Content\Sitemap\SalesChannel\SitemapFileRoute;
use Shopwell\Core\Content\Sitemap\SalesChannel\SitemapRoute;
use Shopwell\Core\Content\Sitemap\ScheduledTask\SitemapGenerateTask;
use Shopwell\Core\Content\Sitemap\ScheduledTask\SitemapGenerateTaskHandler;
use Shopwell\Core\Content\Sitemap\ScheduledTask\SitemapMessageHandler;
use Shopwell\Core\Content\Sitemap\Service\ConfigHandler;
use Shopwell\Core\Content\Sitemap\Service\SitemapExporter;
use Shopwell\Core\Content\Sitemap\Service\SitemapHandleFactory;
use Shopwell\Core\Content\Sitemap\Service\SitemapHandleFactoryInterface;
use Shopwell\Core\Content\Sitemap\Service\SitemapLister;
use Shopwell\Core\Content\Sitemap\Service\SitemapSalesChannelLoader;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SitemapExporter::class)
        ->args([
            tagged_iterator('shopware.sitemap_url_provider'),
            service('cache.system'),
            param('shopware.sitemap.batchsize'),
            service('shopware.filesystem.sitemap'),
            service(SitemapHandleFactoryInterface::class),
            service('event_dispatcher'),
            service(CartRuleLoader::class),
        ]);

    $services->set(SitemapLister::class)
        ->args([
            service('shopware.filesystem.sitemap'),
            service('shopware.asset.sitemap'),
            service(ClockInterface::class),
        ]);

    $services->set(ConfigHandler::class)
        ->args([
            tagged_iterator('shopware.sitemap.config_handler'),
        ]);

    $services->set(SitemapHandleFactoryInterface::class, SitemapHandleFactory::class)
        ->args([
            service('event_dispatcher'),
        ]);

    $services->set(SitemapRoute::class)
        ->public()
        ->args([
            service(SitemapLister::class),
            service(SystemConfigService::class),
            service(SitemapExporter::class),
            service(CacheTagCollector::class),
        ]);

    $services->set(SitemapFileRoute::class)
        ->public()
        ->args([
            service('shopware.filesystem.sitemap'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(HomeUrlProvider::class)
        ->tag('shopware.sitemap_url_provider');

    $services->set(CategoryUrlProvider::class)
        ->args([
            service(ConfigHandler::class),
            service(Connection::class),
            service(CategoryDefinition::class),
            service(IteratorFactory::class),
            service(EntityRouteResolver::class),
            service('event_dispatcher'),
        ])
        ->tag('shopware.sitemap_url_provider');

    $services->set(CustomUrlProvider::class)
        ->args([
            service(ConfigHandler::class),
        ])
        ->tag('shopware.sitemap_url_provider');

    $services->set(ProductUrlProvider::class)
        ->args([
            service(ConfigHandler::class),
            service(Connection::class),
            service(ProductDefinition::class),
            service(IteratorFactory::class),
            service(EntityRouteResolver::class),
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ])
        ->tag('shopware.sitemap_url_provider');

    $services->set(LandingPageUrlProvider::class)
        ->args([
            service(ConfigHandler::class),
            service(Connection::class),
            service(EntityRouteResolver::class),
            service('event_dispatcher'),
        ])
        ->tag('shopware.sitemap_url_provider');

    $services->set(File::class)
        ->args([
            param('shopware.sitemap'),
        ])
        ->tag('shopware.sitemap.config_handler');

    $services->set(SitemapSalesChannelLoader::class)
        ->args([
            service('sales_channel.repository'),
            service('event_dispatcher'),
        ]);

    $services->set(SitemapGenerateCommand::class)
        ->args([
            service(SitemapSalesChannelLoader::class),
            service(SitemapExporter::class),
            service(SalesChannelContextFactory::class),
        ])
        ->tag('console.command');

    $services->set(SitemapGenerateTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(SitemapGenerateTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(SitemapSalesChannelLoader::class),
            service(SystemConfigService::class),
            service('messenger.default_bus'),
        ])
        ->tag('messenger.message_handler');

    $services->set(SitemapMessageHandler::class)
        ->args([
            service(SalesChannelContextFactory::class),
            service(SitemapExporter::class),
            service('logger'),
            service(SystemConfigService::class),
        ])
        ->tag('messenger.message_handler');
};
