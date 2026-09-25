<?php declare(strict_types=1);

namespace Shopwell\Storefront\DependencyInjection;

use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopwell\Core\Content\Category\Service\CategoryUrlGenerator;
use Shopwell\Core\Content\LandingPage\LandingPageDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopwell\Core\Content\Seo\SeoUrlUpdater;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\LandingPageSeoUrlRoute;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Shopwell\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlUpdateListener;
use Shopwell\Storefront\Framework\Seo\SeoUrlRouteNameEnumProvider;
use Shopwell\Storefront\Framework\Seo\StorefrontCategoryUrlGenerator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(ProductPageSeoUrlRoute::class)
        ->args([
            service(ProductDefinition::class),
        ])
        ->tag('shopware.seo_url.route');

    $services->set(NavigationPageSeoUrlRoute::class)
        ->args([
            service(CategoryDefinition::class),
            service(CategoryBreadcrumbBuilder::class),
        ])
        ->tag('shopware.seo_url.route');

    $services->set(LandingPageSeoUrlRoute::class)
        ->args([
            service(LandingPageDefinition::class),
        ])
        ->tag('shopware.seo_url.route');

    $services->set(SeoUrlUpdateListener::class)
        ->args([
            service(SeoUrlUpdater::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SeoUrlRouteNameEnumProvider::class)
        ->args([
            service(SeoUrlRouteRegistry::class),
        ])
        ->tag('shopware.api.enum_provider');

    $services->set(StorefrontCategoryUrlGenerator::class)
        ->decorate(CategoryUrlGenerator::class)
        ->args([
            service(StorefrontCategoryUrlGenerator::class . '.inner'),
            service('router'),
        ]);
};
