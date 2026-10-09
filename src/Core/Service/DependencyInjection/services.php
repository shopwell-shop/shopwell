<?php declare(strict_types=1);

namespace Shopwell\Core\Service\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\App\ActiveAppsLoader;
use Shopwell\Core\Framework\App\AppExtractor;
use Shopwell\Core\Framework\App\AppStorage;
use Shopwell\Core\Framework\App\Command\UninstallAppCommand;
use Shopwell\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopwell\Core\Framework\App\Lifecycle\AppManager;
use Shopwell\Core\Framework\App\Manifest\ManifestFactory;
use Shopwell\Core\Framework\App\Privileges\Privileges;
use Shopwell\Core\Framework\App\ShopId\ShopIdProvider;
use Shopwell\Core\Framework\Notification\NotificationService;
use Shopwell\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopwell\Core\Framework\Store\Services\FirstRunWizardService;
use Shopwell\Core\Service\AllServiceInstaller;
use Shopwell\Core\Service\Api\PermissionController;
use Shopwell\Core\Service\Api\ServiceController;
use Shopwell\Core\Service\Command\Install;
use Shopwell\Core\Service\Command\UninstallAppCommandDecorator;
use Shopwell\Core\Service\LifecycleManager;
use Shopwell\Core\Service\MessageHandler\InstallServicesHandler;
use Shopwell\Core\Service\MessageHandler\LogConsentToRegistryHandler;
use Shopwell\Core\Service\MessageHandler\UpdateServiceHandler;
use Shopwell\Core\Service\Notification;
use Shopwell\Core\Service\Permission\PermissionsService;
use Shopwell\Core\Service\Requirement\RequirementsValidator;
use Shopwell\Core\Service\Requirement\ServiceConsentRequirement;
use Shopwell\Core\Service\Requirement\ServicesEnabledRequirement;
use Shopwell\Core\Service\Requirement\ShopwellAccountRequirement;
use Shopwell\Core\Service\ScheduledTask\InstallServicesTask;
use Shopwell\Core\Service\ScheduledTask\InstallServicesTaskHandler;
use Shopwell\Core\Service\ServiceClientFactory;
use Shopwell\Core\Service\ServiceHookableEventDescriber;
use Shopwell\Core\Service\ServiceLifecycle;
use Shopwell\Core\Service\ServiceRegistry\Client;
use Shopwell\Core\Service\ServiceRegistry\PermissionLogger;
use Shopwell\Core\Service\ServiceRegistry\RegistryUrlProcessor;
use Shopwell\Core\Service\ServiceSourceResolver;
use Shopwell\Core\Service\ServiceStorage;
use Shopwell\Core\Service\ServiceWebhookPolicy;
use Shopwell\Core\Service\Subscriber\ExtensionCompatibilitiesResolvedSubscriber;
use Shopwell\Core\Service\Subscriber\InstalledExtensionsListingLoadedSubscriber;
use Shopwell\Core\Service\Subscriber\LicenseProviderSubscriber;
use Shopwell\Core\Service\Subscriber\PermissionsSubscriber;
use Shopwell\Core\Service\Subscriber\ServiceLifecycleSubscriber;
use Shopwell\Core\Service\Subscriber\ServiceWriteProtectionSubscriber;
use Shopwell\Core\Service\Subscriber\ShopwellAccountSubscriber;
use Shopwell\Core\Service\Subscriber\SystemUpdateSubscriber;
use Shopwell\Core\Service\TemporaryDirectoryFactory;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();
    $parameters->set('env(SERVICE_REGISTRY_URL)', ServiceExtension::DEFAULT_REGISTRY_URL);
    $parameters->set('env(ENABLE_SERVICES)', 'auto');

    $services = $containerConfigurator->services();

    $services->set(ServiceController::class)
        ->public()
        ->args([
            service(ServiceStorage::class),
            service('messenger.default_bus'),
            service(ServiceLifecycle::class),
            service(LifecycleManager::class),
            service(RequirementsValidator::class),
        ]);

    $services->set(PermissionController::class)
        ->public()
        ->args([
            service(PermissionsService::class),
        ]);

    $services->set(Install::class)
        ->args([
            service(LifecycleManager::class),
        ])
        ->tag('console.command');

    $services->set(RegistryUrlProcessor::class)
        ->args([
            ServiceExtension::DEFAULT_REGISTRY_URL,
            param('shopwell.service_registry.trusted_domains'),
        ])
        ->tag('container.env_var_processor');

    $services->set(Client::class)
        ->args([
            param('shopwell.service_registry.url'),
            env('APP_URL'),
            service('service_registry.http_client'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ServiceLifecycle::class)
        ->args([
            service(AppManager::class),
            service('app.repository'),
            service(ServiceStorage::class),
            service('logger'),
            service(ManifestFactory::class),
            service(ServiceSourceResolver::class),
            service('event_dispatcher'),
            service(RequirementsValidator::class),
            service(Client::class),
            service(ServiceClientFactory::class),
            service(Privileges::class),
        ]);

    $services->set(ServiceStorage::class)
        ->args([
            service('app.repository'),
        ]);

    $services->set(UninstallAppCommandDecorator::class)
        ->decorate(UninstallAppCommand::class)
        ->args([
            service(AppLifecycle::class),
            service(AppStorage::class),
            service(ServiceStorage::class),
            service(ServiceLifecycle::class),
            service(LifecycleManager::class),
        ])
        ->tag('console.command', ['command' => 'app:uninstall']);

    $services->set(ServiceClientFactory::class)
        ->args([
            service(HttpClientInterface::class),
            service(Client::class),
            param('kernel.shopwell_version'),
        ]);

    $services->set(AllServiceInstaller::class)
        ->args([
            service(Client::class),
            service(ServiceStorage::class),
            service(ServiceLifecycle::class),
            service('messenger.bus.default'),
            service('event_dispatcher'),
            service('logger'),
        ]);

    $services->set(InstallServicesTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(InstallServicesTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(LifecycleManager::class),
            service(FirstRunWizardService::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(UpdateServiceHandler::class)
        ->args([
            service(ServiceLifecycle::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(InstallServicesHandler::class)
        ->args([
            service(LifecycleManager::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(ServiceSourceResolver::class)
        ->args([
            service(Client::class),
            service(TemporaryDirectoryFactory::class),
            service(AppExtractor::class),
            service(Filesystem::class),
        ])
        ->tag('app.source_resolver', ['priority' => 100]);

    $services->set(ExtensionCompatibilitiesResolvedSubscriber::class)
        ->args([
            service(Client::class),
            service(AbstractExtensionDataProvider::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(InstalledExtensionsListingLoadedSubscriber::class)
        ->args([
            service('app.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SystemUpdateSubscriber::class)
        ->args([
            service(LifecycleManager::class),
            service('logger'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(TemporaryDirectoryFactory::class)
        ->args([
            param('kernel.project_dir'),
        ]);

    $services->set(LicenseProviderSubscriber::class)
        ->args([
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ServiceHookableEventDescriber::class)
        ->tag('shopwell.hookable_event.describer');

    $services->set(ServiceWebhookPolicy::class)
        ->args([service(ActiveAppsLoader::class)])
        ->tag('shopwell.webhook.policy');

    $services->set(PermissionsService::class)
        ->args([
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(PermissionLogger::class),
            service(ClockInterface::class),
        ]);

    $services->set(ServiceConsentRequirement::class)
        ->args([
            service(PermissionsService::class),
        ])
        ->tag('shopwell.service.requirement');

    $services->set(ServicesEnabledRequirement::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('shopwell.service.requirement');

    $services->set(ShopwellAccountRequirement::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopwell.service.requirement');

    $services->set(RequirementsValidator::class)
        ->args([
            tagged_iterator('shopwell.service.requirement', null, 'getName'),
        ]);

    $services->set(LifecycleManager::class)
        ->args([
            env('ENABLE_SERVICES'),
            param('kernel.environment'),
            service(SystemConfigService::class),
            service(ServiceStorage::class),
            service(ServiceLifecycle::class),
            service(AllServiceInstaller::class),
            service(PermissionsService::class),
            service(Client::class),
        ]);

    $services->set(ServiceLifecycleSubscriber::class)
        ->args([
            service(Notification::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(PermissionsSubscriber::class)
        ->args([
            service(ServiceLifecycle::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ShopwellAccountSubscriber::class)
        ->args([
            service(ServiceLifecycle::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ServiceWriteProtectionSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(Notification::class)
        ->args([
            service(NotificationService::class),
        ]);

    $services->set(PermissionLogger::class)
        ->args([
            service(Client::class),
            service('messenger.bus.default'),
            service(ShopIdProvider::class),
            service(SystemConfigService::class),
        ]);

    $services->set(LogConsentToRegistryHandler::class)
        ->args([
            service(PermissionLogger::class),
        ])
        ->tag('messenger.message_handler');
};
