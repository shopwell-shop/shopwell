<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingDefinition;
use Shopwell\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition;
use Shopwell\Core\System\Currency\Api\CurrencyIsoCodeFkResolver;
use Shopwell\Core\System\Currency\CurrencyDefinition;
use Shopwell\Core\System\Currency\CurrencyExceptionHandler;
use Shopwell\Core\System\Currency\CurrencyFormatter;
use Shopwell\Core\System\Currency\CurrencyLoadSubscriber;
use Shopwell\Core\System\Currency\CurrencyValidator;
use Shopwell\Core\System\Currency\Rule\CurrencyRule;
use Shopwell\Core\System\Currency\SalesChannel\CurrencyRoute;
use Shopwell\Core\System\Currency\SalesChannel\SalesChannelCurrencyDefinition;
use Shopwell\Core\System\Locale\LanguageLocaleCodeProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CurrencyDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CurrencyCountryRoundingDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(SalesChannelCurrencyDefinition::class)
        ->tag('shopwell.sales_channel.entity.definition');

    $services->set(CurrencyTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CurrencyLoadSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(CurrencyValidator::class)
        ->tag('kernel.event_subscriber');

    $services->set(CurrencyExceptionHandler::class)
        ->tag('shopwell.dal.exception_handler');

    $services->set(CurrencyRule::class)
        ->tag('shopwell.rule.definition');

    $services->set(CurrencyFormatter::class)
        ->public()
        ->args([
            service(LanguageLocaleCodeProvider::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CurrencyRoute::class)
        ->public()
        ->args([
            service('sales_channel.currency.repository'),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(CurrencyIsoCodeFkResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopwell.sync.fk_resolver');
};
