<?php declare(strict_types=1);

namespace Shopwell\Core\Content\DependencyInjection;

use Shopwell\Core\Content\Breadcrumb\SalesChannel\BreadcrumbRoute;
use Shopwell\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(BreadcrumbRoute::class)
        ->public()
        ->args([
            service(CategoryBreadcrumbBuilder::class),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
        ]);
};
