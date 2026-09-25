<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\System\Language\CachedLanguageLoader;
use Shopwell\Core\System\Language\LanguageDefinition;
use Shopwell\Core\System\Language\LanguageExceptionHandler;
use Shopwell\Core\System\Language\LanguageLoader;
use Shopwell\Core\System\Language\LanguageValidator;
use Shopwell\Core\System\Language\Rule\LanguageRule;
use Shopwell\Core\System\Language\SalesChannel\LanguageRoute;
use Shopwell\Core\System\Language\SalesChannel\SalesChannelLanguageDefinition;
use Shopwell\Core\System\Language\SalesChannelLanguageLoader;
use Shopwell\Core\System\Language\TranslationValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(LanguageDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SalesChannelLanguageDefinition::class)
        ->tag('shopwell.sales_channel.entity.definition');

    $services->set(LanguageValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(LanguageLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(SalesChannelLanguageLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(CachedLanguageLoader::class)
        ->decorate(LanguageLoader::class)
        ->args([
            service(CachedLanguageLoader::class . '.inner'),
            service('cache.object'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(TranslationValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(LanguageExceptionHandler::class)
        ->tag('shopwell.dal.exception_handler');

    $services->set(LanguageRoute::class)
        ->public()
        ->args([
            service('sales_channel.language.repository'),
            service(CacheTagCollector::class),
        ]);

    $services->set(LanguageRule::class)
        ->tag('shopwell.rule.definition');
};
