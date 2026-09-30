<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\App\AppStorage;
use Shopwell\Core\Framework\App\Delta\AppConfirmationDeltaProvider;
use Shopwell\Core\Framework\App\InAppPurchases\Gateway\InAppPurchasesGateway;
use Shopwell\Core\Framework\App\InAppPurchases\Payload\InAppPurchasesPayloadService;
use Shopwell\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopwell\Core\Framework\App\Lifecycle\AppLoader;
use Shopwell\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopwell\Core\Framework\App\Source\SourceResolver;
use Shopwell\Core\Framework\JWT\JWTDecoder;
use Shopwell\Core\Framework\Plugin\PluginLifecycleService;
use Shopwell\Core\Framework\Plugin\PluginManagementService;
use Shopwell\Core\Framework\Plugin\PluginService;
use Shopwell\Core\Framework\Store\Api\ExtensionStoreActionsController;
use Shopwell\Core\Framework\Store\Api\ExtensionStoreDataController;
use Shopwell\Core\Framework\Store\Api\ExtensionStoreLicensesController;
use Shopwell\Core\Framework\Store\Api\FirstRunWizardController;
use Shopwell\Core\Framework\Store\Api\StoreController;
use Shopwell\Core\Framework\Store\Authentication\FrwRequestOptionsProvider;
use Shopwell\Core\Framework\Store\Authentication\LocaleProvider;
use Shopwell\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopwell\Core\Framework\Store\Command\StoreDownloadCommand;
use Shopwell\Core\Framework\Store\Command\StoreLoginCommand;
use Shopwell\Core\Framework\Store\InAppPurchase;
use Shopwell\Core\Framework\Store\InAppPurchase\Api\InAppPurchasesController;
use Shopwell\Core\Framework\Store\InAppPurchase\Handler\InAppPurchaseUpdateHandler;
use Shopwell\Core\Framework\Store\InAppPurchase\InAppPurchaseUpdateTask;
use Shopwell\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseProvider;
use Shopwell\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseUpdater;
use Shopwell\Core\Framework\Store\InAppPurchase\Services\KeyFetcher;
use Shopwell\Core\Framework\Store\InAppPurchase\Subscriber\InAppPurchaseConfigSubscriber;
use Shopwell\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopwell\Core\Framework\Store\Services\AbstractExtensionStoreLicensesService;
use Shopwell\Core\Framework\Store\Services\AbstractStoreAppLifecycleService;
use Shopwell\Core\Framework\Store\Services\ExtensionDataProvider;
use Shopwell\Core\Framework\Store\Services\ExtensionDownloader;
use Shopwell\Core\Framework\Store\Services\ExtensionLifecycleService;
use Shopwell\Core\Framework\Store\Services\ExtensionListingLoader;
use Shopwell\Core\Framework\Store\Services\ExtensionLoader;
use Shopwell\Core\Framework\Store\Services\ExtensionStoreLicensesService;
use Shopwell\Core\Framework\Store\Services\FirstRunWizardClient;
use Shopwell\Core\Framework\Store\Services\FirstRunWizardService;
use Shopwell\Core\Framework\Store\Services\InstanceService;
use Shopwell\Core\Framework\Store\Services\RetryFailedStoreRequestMiddleware;
use Shopwell\Core\Framework\Store\Services\ShopSecretInvalidMiddleware;
use Shopwell\Core\Framework\Store\Services\StoreAppLifecycleService;
use Shopwell\Core\Framework\Store\Services\StoreClient;
use Shopwell\Core\Framework\Store\Services\StoreClientFactory;
use Shopwell\Core\Framework\Store\Services\StoreService;
use Shopwell\Core\Framework\Store\Services\StoreSessionExpiredMiddleware;
use Shopwell\Core\Framework\Store\Services\TrackingEventClient;
use Shopwell\Core\Framework\Store\Subscriber\ExtensionChangedSubscriber;
use Shopwell\Core\Framework\Store\Subscriber\LicenseHostChangedSubscriber;
use Shopwell\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopwell\Core\System\SystemConfig\Service\SystemConfigDefinitionService;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();
    $parameters->set('env(INSTANCE_ID)', '');
    $parameters->set('instance_id', env('INSTANCE_ID'));
    $parameters->set('in_app_purchases.active_purchases', '/swplatform/inappfeatures/purchases');
    $parameters->set('shopwell.store_endpoints', [
        'my_extensions' => '/swplatform/licenseenvironment',
        'my_plugin_updates' => '/swplatform/pluginupdates',
        'environment_information' => '/swplatform/environmentinformation',
        'updater_extension_compatibility' => '/swplatform/autoupdate',
        'updater_permission' => '/swplatform/autoupdate/permission',
        'plugin_download' => '/swplatform/pluginfiles/{pluginName}',
        'app_generate_signature' => '/swplatform/generatesignature',
        'cancel_license' => '/swplatform/pluginlicenses/%s/cancel',
        'login' => '/swplatform/login',
        'create_rating' => '/swplatform/extensionstore/extensions/%s/ratings',
        'user_info' => '/swplatform/userinfo',
    ]);

    $services = $containerConfigurator->services();

    $services->set(StoreController::class)
        ->public()
        ->args([
            service(StoreClient::class),
            service('user.repository'),
            service(AbstractExtensionDataProvider::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(FirstRunWizardController::class)
        ->public()
        ->args([
            service(FirstRunWizardService::class),
            service('plugin.repository'),
            service('app.repository'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(FirstRunWizardService::class)
        ->args([
            service(StoreService::class),
            service(SystemConfigService::class),
            service('shopwell.filesystem.public'),
            param('shopwell.store.frw'),
            service('event_dispatcher'),
            service(FirstRunWizardClient::class),
            service('user_config.repository'),
            service(TrackingEventClient::class),
        ]);

    $services->set(StoreClient::class)
        ->args([
            param('shopwell.store_endpoints'),
            service(StoreService::class),
            service(SystemConfigService::class),
            service(StoreRequestOptionsProvider::class),
            service(ExtensionLoader::class),
            service('shopwell.store_client'),
            service(InstanceService::class),
            service('request_stack'),
            service('cache.object'),
            service('event_dispatcher'),
        ]);

    $services->set(FirstRunWizardClient::class)
        ->args([
            service('shopwell.frw_client'),
            service(FrwRequestOptionsProvider::class),
            service(InstanceService::class),
        ]);

    $services->set(StoreService::class)
        ->lazy()
        ->args([
            service('user.repository'),
            service(TrackingEventClient::class),
        ]);

    $services->set(InstanceService::class)
        ->args([
            param('kernel.shopwell_version'),
            param('instance_id'),
        ]);

    $services->set(StoreDownloadCommand::class)
        ->args([
            service(StoreClient::class),
            service('plugin.repository'),
            service(PluginManagementService::class),
            service(PluginLifecycleService::class),
            service('user.repository'),
        ])
        ->tag('console.command');

    $services->set(StoreLoginCommand::class)
        ->args([
            service(StoreClient::class),
            service('user.repository'),
            service(SystemConfigService::class),
        ])
        ->tag('console.command');

    $services->set(LocaleProvider::class)
        ->args([
            service('user.repository'),
        ]);

    $services->set(StoreRequestOptionsProvider::class)
        ->public()
        ->args([
            service('user.repository'),
            service(SystemConfigService::class),
            service(InstanceService::class),
            service(LocaleProvider::class),
        ]);

    $services->set(FrwRequestOptionsProvider::class)
        ->args([
            service(StoreRequestOptionsProvider::class),
            service('user_config.repository'),
        ]);

    $services->set(ExtensionLoader::class)
        ->args([
            service(AppLoader::class),
            service(SourceResolver::class),
            service(SystemConfigDefinitionService::class),
            service(LocaleProvider::class),
            service(LanguageLocaleCodeProvider::class),
            service(InAppPurchase::class),
            service('logger'),
            service('event_dispatcher'),
        ]);

    $services->set(AbstractExtensionDataProvider::class, ExtensionDataProvider::class)
        ->args([
            service(ExtensionLoader::class),
            service('app.repository'),
            service('plugin.repository'),
            service(ExtensionListingLoader::class),
            service('event_dispatcher'),
        ]);

    $services->set(ExtensionListingLoader::class)
        ->args([
            service(StoreClient::class),
        ]);

    $services->set(ExtensionStoreDataController::class)
        ->public()
        ->args([
            service(AbstractExtensionDataProvider::class),
            service('user.repository'),
            service('language.repository'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(AbstractStoreAppLifecycleService::class, StoreAppLifecycleService::class)
        ->args([
            service(StoreClient::class),
            service(AppLoader::class),
            service(AppLifecycle::class),
            service(AppStorage::class),
            tagged_iterator('shopwell.store.extension_removal_validator'),
            service(AppConfirmationDeltaProvider::class),
        ]);

    $services->set(AbstractExtensionStoreLicensesService::class, ExtensionStoreLicensesService::class)
        ->args([
            service(StoreClient::class),
        ]);

    $services->set(ExtensionStoreLicensesController::class)
        ->public()
        ->args([
            service(AbstractExtensionStoreLicensesService::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(ExtensionDownloader::class)
        ->args([
            service('plugin.repository'),
            service(StoreClient::class),
            service(PluginManagementService::class),
        ]);

    $services->set(ExtensionLifecycleService::class)
        ->args([
            service(AbstractStoreAppLifecycleService::class),
            service(PluginService::class),
            service(PluginLifecycleService::class),
            service(PluginManagementService::class),
        ]);

    $services->set(ExtensionStoreActionsController::class)
        ->public()
        ->args([
            service(ExtensionLifecycleService::class),
            service(ExtensionDownloader::class),
            service(PluginService::class),
            service(PluginManagementService::class),
            service(Filesystem::class),
            param('shopwell.deployment.runtime_extension_management'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(StoreClientFactory::class)
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set('shopwell.store_client', Client::class)
        ->lazy()
        ->public()
        ->factory([service(StoreClientFactory::class), 'create'])
        ->args([
            tagged_iterator('shopwell.store_client.middleware'),
        ]);

    $services->set('shopwell.frw_client', Client::class)
        ->lazy()
        ->public()
        ->factory([service(StoreClientFactory::class), 'create'])
        ->args([
            tagged_iterator('shopwell.frw_client.middleware'),
        ]);

    $services->set('shopwell.store_download_client', Client::class);

    $services->set(LicenseHostChangedSubscriber::class)
        ->args([
            service(SystemConfigService::class),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(StoreSessionExpiredMiddleware::class)
        ->public()
        ->args([
            service(Connection::class),
            service('request_stack'),
        ])
        ->tag('shopwell.store_client.middleware');

    $services->set(ShopSecretInvalidMiddleware::class)
        ->public()
        ->args([
            service(Connection::class),
            service(SystemConfigService::class),
        ])
        ->tag('shopwell.store_client.middleware');

    $services->set(RetryFailedStoreRequestMiddleware::class)
        ->public()
        ->tag('shopwell.store_client.middleware');

    $services->set(TrackingEventClient::class)
        ->args([
            service('shopwell.store_client'),
            service(InstanceService::class),
        ]);

    $services->set(InAppPurchase::class)
        ->public()
        ->args([
            service(InAppPurchaseProvider::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(InAppPurchaseProvider::class)
        ->args([
            service(SystemConfigService::class),
            service(JWTDecoder::class),
            service(KeyFetcher::class),
            service('logger'),
            service(ClockInterface::class),
        ]);

    $services->set(InAppPurchasesController::class)
        ->public()
        ->args([
            service(InAppPurchase::class),
            service('app.repository'),
        ]);

    $services->set(InAppPurchaseUpdater::class)
        ->public()
        ->args([
            service('shopwell.store_client'),
            service(SystemConfigService::class),
            param('in_app_purchases.active_purchases'),
            service(StoreRequestOptionsProvider::class),
            service(InAppPurchase::class),
            service('event_dispatcher'),
            service(Connection::class),
            service('logger'),
        ]);

    $services->set(InAppPurchaseUpdateHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(InAppPurchaseUpdater::class),
            service(StoreRequestOptionsProvider::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(InAppPurchaseUpdateTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(InAppPurchasesPayloadService::class)
        ->args([
            service(AppPayloadServiceHelper::class),
            service('shopwell.app_system.guzzle'),
        ]);

    $services->set(InAppPurchasesGateway::class)
        ->args([
            service(InAppPurchasesPayloadService::class),
            service('event_dispatcher'),
        ]);

    $services->set(KeyFetcher::class)
        ->args([
            service('shopwell.store_client'),
            service(StoreRequestOptionsProvider::class),
            service(SystemConfigService::class),
            service('logger'),
        ]);

    $services->set(InAppPurchaseConfigSubscriber::class)
        ->args([
            service('request_stack'),
            service(InAppPurchaseUpdater::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(JWTDecoder::class);

    $services->set(ExtensionChangedSubscriber::class)
        ->args([
            service('cache.object'),
        ]);
};
