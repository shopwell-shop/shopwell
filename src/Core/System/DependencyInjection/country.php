<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopwell\Core\System\Country\Aggregate\CountryState\SalesChannel\SalesChannelCountryStateDefinition;
use Shopwell\Core\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationDefinition;
use Shopwell\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;
use Shopwell\Core\System\Country\CountryDefinition;
use Shopwell\Core\System\Country\SalesChannel\CountryRoute;
use Shopwell\Core\System\Country\SalesChannel\CountryStateRoute;
use Shopwell\Core\System\Country\SalesChannel\SalesChannelCountryDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CountryDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SalesChannelCountryDefinition::class)
        ->tag('shopwell.sales_channel.entity.definition');

    $services->set(CountryStateDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SalesChannelCountryStateDefinition::class)
        ->tag('shopwell.sales_channel.entity.definition');

    $services->set(CountryStateTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CountryTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CountryRoute::class)
        ->public()
        ->args([
            service('sales_channel.country.repository'),
            service('event_dispatcher'),
            service(CacheTagCollector::class),
        ]);

    $services->set(CountryStateRoute::class)
        ->public()
        ->args([
            service('country_state.repository'),
            service('event_dispatcher'),
            service(CacheTagCollector::class),
        ]);
};
