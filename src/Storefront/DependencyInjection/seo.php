<?php declare(strict_types=1);

namespace Shopwell\Storefront\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopwell\Core\Content\Category\Service\CategoryUrlGenerator;
use Shopwell\Core\Content\LandingPage\LandingPageDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Seo\SeoUrlGenerator;
use Shopwell\Core\Content\Seo\SeoUrlPersister;
use Shopwell\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopwell\Core\Content\Seo\SeoUrlUpdater;
use Shopwell\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopwell\Core\Framework\App\Feature\AppFeatureStorage;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Routing\Validation\RouteBlocklistService;
use Shopwell\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopwell\Storefront\Framework\Seo\App\AppSeoUrlClaims;
use Shopwell\Storefront\Framework\Seo\App\AppSeoUrlIndexer;
use Shopwell\Storefront\Framework\Seo\App\AppSeoUrlLifecycleHandler;
use Shopwell\Storefront\Framework\Seo\App\AppSeoUrlRouteLoader;
use Shopwell\Storefront\Framework\Seo\App\AppSeoUrlRouteProvider;
use Shopwell\Storefront\Framework\Seo\App\AppSeoUrlSynchronizer;
use Shopwell\Storefront\Framework\Seo\App\EntitySeoUrlAppFeatureDefinition;
use Shopwell\Storefront\Framework\Seo\App\SeoUrlAppFeatureDefinition;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\LandingPageSeoUrlRoute;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlUpdateListener;
use Shopwell\Storefront\Framework\Seo\SeoUrlRouteNameEnumProvider;
use Shopwell\Storefront\Framework\Seo\StorefrontCategoryUrlGenerator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Messenger\MessageBusInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(ProductPageSeoUrlRoute::class)
        ->args([
            service(ProductDefinition::class),
        ])
        ->tag('shopwell.seo_url.route');

    $services->set(NavigationPageSeoUrlRoute::class)
        ->args([
            service(CategoryDefinition::class),
            service(CategoryBreadcrumbBuilder::class),
        ])
        ->tag('shopwell.seo_url.route');

    $services->set(LandingPageSeoUrlRoute::class)
        ->args([
            service(LandingPageDefinition::class),
        ])
        ->tag('shopwell.seo_url.route');

    $services->set(SeoUrlUpdateListener::class)
        ->args([
            service(SeoUrlUpdater::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AppSeoUrlClaims::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(SeoUrlAppFeatureDefinition::class)
        ->args([
            service(Connection::class),
            service(RouteBlocklistService::class),
            service(AppSeoUrlClaims::class),
        ])
        ->tag('shopwell.app_feature.definition');

    $services->set(EntitySeoUrlAppFeatureDefinition::class)
        ->args([
            service('seo_url_template.repository'),
            service(AppSeoUrlClaims::class),
            service(DefinitionInstanceRegistry::class),
            service(SeoUrlGenerator::class),
            service(AclCriteriaValidator::class),
        ])
        ->tag('shopwell.app_feature.definition');

    $services->set(AppSeoUrlRouteProvider::class)
        ->args([
            service(AppFeatureStorage::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(AppSeoUrlRouteLoader::class)
        ->args([
            service(AppSeoUrlRouteProvider::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopwell.seo_url.route_loader');

    $services->set(AppSeoUrlIndexer::class)
        ->args([
            service(AppSeoUrlRouteProvider::class),
            service(SeoUrlUpdater::class),
            service(MessageBusInterface::class),
            service(AppSeoUrlSynchronizer::class),
        ])
        ->tag('shopwell.entity_indexer');

    $services->set(AppSeoUrlSynchronizer::class)
        ->args([
            service(AppFeatureStorage::class),
            service('sales_channel.repository'),
            service(SeoUrlPersister::class),
            service(LanguageLocaleCodeProvider::class),
            service('router.default'),
            service('request_stack'),
        ]);

    $services->set(AppSeoUrlLifecycleHandler::class)
        ->args([
            service(AppFeatureStorage::class),
            service(Connection::class),
            service('seo_url_template.repository'),
            service(MessageBusInterface::class),
            service(AppSeoUrlSynchronizer::class),
        ])
        ->tag('shopwell.app_lifecycle.handler', ['priority' => -1250])
        ->tag('kernel.event_subscriber');

    $services->set(SeoUrlRouteNameEnumProvider::class)
        ->args([
            service(SeoUrlRouteRegistry::class),
        ])
        ->tag('shopwell.api.enum_provider');

    $services->set(StorefrontCategoryUrlGenerator::class)
        ->decorate(CategoryUrlGenerator::class)
        ->args([
            service(StorefrontCategoryUrlGenerator::class . '.inner'),
            service('router'),
        ]);
};
