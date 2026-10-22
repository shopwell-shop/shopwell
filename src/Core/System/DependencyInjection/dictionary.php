<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\Aggregate\DictionaryEntryTranslation\DictionaryEntryTranslationDefinition;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryDefinition;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\SalesChannelDictionaryEntryDefinition;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryTypeTranslation\DictionaryTypeTranslationDefinition;
use Shopwell\Core\System\Dictionary\Api\DictionaryEntryTechnicalNameFkResolver;
use Shopwell\Core\System\Dictionary\DictionaryTypeDefinition;
use Shopwell\Core\System\Dictionary\SalesChannel\DictionaryRoute;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(DictionaryTypeDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(DictionaryTypeTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(DictionaryEntryDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(DictionaryEntryTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SalesChannelDictionaryEntryDefinition::class)
        ->tag('shopwell.sales_channel.entity.definition');

    $services->set(DictionaryRoute::class)
        ->public()
        ->args([
            service('sales_channel.dictionary_entry.repository'),
            service(Connection::class),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(DictionaryEntryTechnicalNameFkResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopwell.sync.fk_resolver');
};
