<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\App\Source\SourceResolver;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\SystemConfig\Api\SystemConfigController;
use Shopwell\Core\System\SystemConfig\CachedSystemConfigLoader;
use Shopwell\Core\System\SystemConfig\Command\ConfigGet;
use Shopwell\Core\System\SystemConfig\Command\ConfigSet;
use Shopwell\Core\System\SystemConfig\ConfiguredSystemConfigLoader;
use Shopwell\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;
use Shopwell\Core\System\SystemConfig\MemoizedSystemConfigLoader;
use Shopwell\Core\System\SystemConfig\SalesChannel\ShopSettingsRoute;
use Shopwell\Core\System\SystemConfig\Service\AppConfigReader;
use Shopwell\Core\System\SystemConfig\Service\ConfigurationService;
use Shopwell\Core\System\SystemConfig\Service\SystemConfigDefinitionService;
use Shopwell\Core\System\SystemConfig\Store\MemoizedSystemConfigStore;
use Shopwell\Core\System\SystemConfig\SymfonySystemConfigService;
use Shopwell\Core\System\SystemConfig\SystemConfigDefinition;
use Shopwell\Core\System\SystemConfig\SystemConfigLoader;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\System\SystemConfig\Util\ConfigReader;
use Shopwell\Core\System\SystemConfig\Validation\SystemConfigValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\KernelInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SystemConfigValidator::class)
        ->args([
            service(SystemConfigDefinitionService::class),
            service(DataValidator::class),
        ])
        ->tag('shopwell.system_config.validation');

    $services->set(SystemConfigDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set('kernel.bundles', \Iterator::class)
        ->factory([service('kernel'), 'getBundles']);

    $services->set(AppConfigReader::class)
        ->args([
            service(SourceResolver::class),
            service(ConfigReader::class),
        ]);

    $services->set(ConfigurationService::class)
        ->args([
            service(SystemConfigService::class),
            service(SystemConfigDefinitionService::class),
        ])
        ->tag('shopwell.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(SystemConfigDefinitionService::class)
        ->args([
            service('kernel.bundles'),
            service(ConfigReader::class),
            service(AppConfigReader::class),
            service('app.repository'),
            service(SystemConfigService::class),
            service('logger'),
        ]);

    $services->set(ConfigReader::class);

    $services->set(SystemConfigController::class)
        ->public()
        ->args([
            // @deprecated tag:v6.8.0 - ConfigurationService will be removed
            service(ConfigurationService::class)->nullOnInvalid(),
            service(SystemConfigDefinitionService::class),
            service(SystemConfigService::class),
            service(SystemConfigValidator::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(SystemConfigService::class)
        ->public()
        ->lazy()
        ->args([
            service(Connection::class),
            service(ConfigReader::class),
            service(SystemConfigLoader::class),
            service('event_dispatcher'),
            service(SymfonySystemConfigService::class),
            service(CacheTagCollector::class),
            service(ClockInterface::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ShopSettingsRoute::class)
        ->public()
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set(MemoizedSystemConfigStore::class)
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(SymfonySystemConfigService::class)
        ->args([
            param('shopwell.system_config'),
        ]);

    $services->set(SystemConfigLoader::class)
        ->args([
            service(Connection::class),
            service(KernelInterface::class),
        ]);

    $services->set(ConfiguredSystemConfigLoader::class)
        ->decorate(SystemConfigLoader::class, null, -1500)
        ->args([
            service(ConfiguredSystemConfigLoader::class . '.inner'),
            service(SymfonySystemConfigService::class),
        ]);

    $services->set(CachedSystemConfigLoader::class)
        ->decorate(SystemConfigLoader::class, null, -1000)
        ->args([
            service(CachedSystemConfigLoader::class . '.inner'),
            service('cache.object'),
        ]);

    $services->set(MemoizedSystemConfigLoader::class)
        ->decorate(SystemConfigLoader::class, null, -2000)
        ->args([
            service(MemoizedSystemConfigLoader::class . '.inner'),
            service(MemoizedSystemConfigStore::class),
        ]);

    $services->set(SystemConfigFacadeHookFactory::class)
        ->public()
        ->args([
            service(SystemConfigService::class),
            service(Connection::class),
        ]);

    $services->set(ConfigGet::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('console.command');

    $services->set(ConfigSet::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('console.command');
};
