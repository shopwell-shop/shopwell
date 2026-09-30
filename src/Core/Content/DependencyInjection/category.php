<?php declare(strict_types=1);

namespace Shopwell\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\Category\Aggregate\CategoryTag\CategoryTagDefinition;
use Shopwell\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Category\Cms\CategoryNameCmsElementResolver;
use Shopwell\Core\Content\Category\Cms\CategoryNavigationCmsElementResolver;
use Shopwell\Core\Content\Category\DataAbstractionLayer\CategoryBreadcrumbUpdater;
use Shopwell\Core\Content\Category\DataAbstractionLayer\CategoryIndexer;
use Shopwell\Core\Content\Category\DataAbstractionLayer\CategoryNonExistentExceptionHandler;
use Shopwell\Core\Content\Category\SalesChannel\CategoryListRoute;
use Shopwell\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopwell\Core\Content\Category\SalesChannel\NavigationRoute;
use Shopwell\Core\Content\Category\SalesChannel\SalesChannelCategoryDefinition;
use Shopwell\Core\Content\Category\SalesChannel\TreeBuildingNavigationRoute;
use Shopwell\Core\Content\Category\Service\CachedDefaultCategoryLevelLoader;
use Shopwell\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopwell\Core\Content\Category\Service\CategoryUrlGenerator;
use Shopwell\Core\Content\Category\Service\DefaultCategoryLevelLoader;
use Shopwell\Core\Content\Category\Service\NavigationLoader;
use Shopwell\Core\Content\Category\Subscriber\CategorySubscriber;
use Shopwell\Core\Content\Category\Subscriber\CategoryTreeMovedSubscriber;
use Shopwell\Core\Content\Category\Tree\CategoryTreePathResolver;
use Shopwell\Core\Content\Category\Validation\EntryPointValidator;
use Shopwell\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopwell\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Shopwell\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Util\HtmlSanitizer;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CategoryDefinition::class)
        ->tag('shopwell.entity.definition')
        ->tag('shopwell.entity.hookable');

    $services->set(CategoryTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CategoryTagDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SalesChannelCategoryDefinition::class)
        ->tag('shopwell.sales_channel.entity.definition');

    $services->set(NavigationLoader::class)
        ->args([
            service('event_dispatcher'),
            service(NavigationRoute::class),
        ]);

    $services->set(NavigationRoute::class)
        ->public()
        ->args([
            service(Connection::class),
            service('sales_channel.category.repository'),
            service(CacheTagCollector::class),
            service(CategoryTreePathResolver::class),
            service(DefaultCategoryLevelLoader::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(DefaultCategoryLevelLoader::class)
        ->args([
            service('sales_channel.category.repository'),
        ]);

    $services->set(CachedDefaultCategoryLevelLoader::class)
        ->decorate(DefaultCategoryLevelLoader::class)
        ->args([
            service('cache.object'),
            service('event_dispatcher'),
            service(CachedDefaultCategoryLevelLoader::class . '.inner'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CategoryTreePathResolver::class);

    $services->set(TreeBuildingNavigationRoute::class)
        ->decorate(NavigationRoute::class, null, -2000)
        ->public()
        ->args([
            service(TreeBuildingNavigationRoute::class . '.inner'),
        ]);

    $services->set(CategoryRoute::class)
        ->public()
        ->args([
            service('sales_channel.category.repository'),
            service(SalesChannelCmsPageLoader::class),
            service(EntityCmsSlotConfigInheritanceBuilder::class),
            service(SalesChannelCategoryDefinition::class),
            service(CacheTagCollector::class),
        ]);

    $services->set(CategoryListRoute::class)
        ->public()
        ->args([
            service('sales_channel.category.repository'),
        ]);

    $services->set(CategoryIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('category.repository'),
            service(ChildCountUpdater::class),
            service(TreeUpdater::class),
            service(CategoryBreadcrumbUpdater::class),
            service('event_dispatcher'),
            service('messenger.default_bus'),
        ])
        // Must run before ProductIndexer so ProductCategoryDenormalizer can include parent category paths.
        ->tag('shopwell.entity_indexer', ['priority' => 105]);

    $services->set(CategoryBreadcrumbUpdater::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(TreeUpdater::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(Connection::class),
        ]);

    $services->set(CategoryBreadcrumbBuilder::class)
        ->args([
            service('category.repository'),
            service('sales_channel.product.repository'),
            service(Connection::class),
            service(EntityRouteResolver::class),
        ]);

    $services->set(CategoryUrlGenerator::class)
        ->args([
            service(EntityRouteResolver::class),
        ]);

    $services->set(EntryPointValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CategorySubscriber::class)
        ->args([
            service(SystemConfigService::class),
            service(CategoryUrlGenerator::class),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CategoryTreeMovedSubscriber::class)
        ->args([
            service(EntityIndexerRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CategoryNonExistentExceptionHandler::class)
        ->tag('shopwell.dal.exception_handler');

    $services->set(CategoryNavigationCmsElementResolver::class)
        ->args([
            service(NavigationLoader::class),
        ])
        ->tag('shopwell.cms.data_resolver');

    $services->set(CategoryNameCmsElementResolver::class)
        ->args([
            service(HtmlSanitizer::class),
        ])
        ->tag('shopwell.cms.data_resolver');
};
