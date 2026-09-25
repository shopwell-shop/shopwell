<?php declare(strict_types=1);

namespace Shopwell\Core\Content\DependencyInjection;

use Shopwell\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopwell\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Shopwell\Core\Content\LandingPage\Aggregate\LandingPageSalesChannel\LandingPageSalesChannelDefinition;
use Shopwell\Core\Content\LandingPage\Aggregate\LandingPageTag\LandingPageTagDefinition;
use Shopwell\Core\Content\LandingPage\Aggregate\LandingPageTranslation\LandingPageTranslationDefinition;
use Shopwell\Core\Content\LandingPage\DataAbstractionLayer\LandingPageIndexer;
use Shopwell\Core\Content\LandingPage\LandingPageDefinition;
use Shopwell\Core\Content\LandingPage\LandingPageValidator;
use Shopwell\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Shopwell\Core\Content\LandingPage\SalesChannel\SalesChannelLandingPageDefinition;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(LandingPageDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(LandingPageTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(LandingPageTagDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(LandingPageSalesChannelDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(LandingPageIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('landing_page.repository'),
            service('event_dispatcher'),
        ])
        ->tag('shopwell.entity_indexer', ['priority' => 1000]);

    $services->set(SalesChannelLandingPageDefinition::class)
        ->tag('shopwell.sales_channel.entity.definition');

    $services->set(LandingPageRoute::class)
        ->public()
        ->args([
            service('sales_channel.landing_page.repository'),
            service(SalesChannelCmsPageLoader::class),
            service(EntityCmsSlotConfigInheritanceBuilder::class),
            service(SalesChannelLandingPageDefinition::class),
            service(CacheTagCollector::class),
        ]);

    $services->set(LandingPageValidator::class)
        ->args([
            service('validator'),
        ])
        ->tag('kernel.event_subscriber');
};
