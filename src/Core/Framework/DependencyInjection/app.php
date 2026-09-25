<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Gateway\Command\Executor\CheckoutGatewayCommandExecutor;
use Shopwell\Core\Checkout\Gateway\Command\Registry\CheckoutGatewayCommandRegistry;
use Shopwell\Core\Content\Media\File\TrustedUrlResolver;
use Shopwell\Core\Content\Media\MediaService;
use Shopwell\Core\Framework\Adapter\Asset\AssetService;
use Shopwell\Core\Framework\Adapter\Cache\CacheClearer;
use Shopwell\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopwell\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopwell\Core\Framework\App\ActionButton\ActionButtonLoader;
use Shopwell\Core\Framework\App\ActionButton\AppActionLoader;
use Shopwell\Core\Framework\App\ActionButton\Executor;
use Shopwell\Core\Framework\App\ActionButton\Response\ActionButtonResponseFactory;
use Shopwell\Core\Framework\App\ActionButton\Response\NotificationResponseFactory;
use Shopwell\Core\Framework\App\ActionButton\Response\OpenModalResponseFactory;
use Shopwell\Core\Framework\App\ActionButton\Response\OpenNewTabResponseFactory;
use Shopwell\Core\Framework\App\ActionButton\Response\ReloadDataResponseFactory;
use Shopwell\Core\Framework\App\ActiveAppsLoader;
use Shopwell\Core\Framework\App\Aggregate\ActionButton\ActionButtonDefinition;
use Shopwell\Core\Framework\App\Aggregate\ActionButtonTranslation\ActionButtonTranslationDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppScriptConditionTranslation\AppScriptConditionTranslationDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppShippingMethod\AppShippingMethodDefinition;
use Shopwell\Core\Framework\App\Aggregate\AppTranslation\AppTranslationDefinition;
use Shopwell\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockDefinition;
use Shopwell\Core\Framework\App\Aggregate\CmsBlockTranslation\AppCmsBlockTranslationDefinition;
use Shopwell\Core\Framework\App\Aggregate\FlowAction\AppFlowActionDefinition;
use Shopwell\Core\Framework\App\Aggregate\FlowActionTranslation\AppFlowActionTranslationDefinition;
use Shopwell\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventDefinition;
use Shopwell\Core\Framework\App\Api\AppActionController;
use Shopwell\Core\Framework\App\Api\AppCmsController;
use Shopwell\Core\Framework\App\Api\AppJWTGenerateRoute;
use Shopwell\Core\Framework\App\Api\AppPrivilegeController;
use Shopwell\Core\Framework\App\Api\AppSecretRotationController;
use Shopwell\Core\Framework\App\Api\ShopIdController;
use Shopwell\Core\Framework\App\Api\VerifyShopController;
use Shopwell\Core\Framework\App\AppArchiveValidator;
use Shopwell\Core\Framework\App\AppDefinition;
use Shopwell\Core\Framework\App\AppDownloader;
use Shopwell\Core\Framework\App\AppExtractor;
use Shopwell\Core\Framework\App\AppLocaleProvider;
use Shopwell\Core\Framework\App\AppSecretResolver;
use Shopwell\Core\Framework\App\AppService;
use Shopwell\Core\Framework\App\AppStorage;
use Shopwell\Core\Framework\App\Checkout\Gateway\AppCheckoutGateway;
use Shopwell\Core\Framework\App\Checkout\Payload\AppCheckoutGatewayPayloadService;
use Shopwell\Core\Framework\App\Cms\BlockTemplateLoader;
use Shopwell\Core\Framework\App\Command\ActivateAppCommand;
use Shopwell\Core\Framework\App\Command\AppListCommand;
use Shopwell\Core\Framework\App\Command\AppPrinter;
use Shopwell\Core\Framework\App\Command\AppUrlVerificationStatusCommand;
use Shopwell\Core\Framework\App\Command\AppUrlVerifyCommand;
use Shopwell\Core\Framework\App\Command\ChangeShopIdCommand;
use Shopwell\Core\Framework\App\Command\CheckShopIdCommand;
use Shopwell\Core\Framework\App\Command\CreateAppCommand;
use Shopwell\Core\Framework\App\Command\DeactivateAppCommand;
use Shopwell\Core\Framework\App\Command\InstallAppCommand;
use Shopwell\Core\Framework\App\Command\RefreshAppCommand;
use Shopwell\Core\Framework\App\Command\RotateAppSecretCommand;
use Shopwell\Core\Framework\App\Command\UninstallAppCommand;
use Shopwell\Core\Framework\App\Command\ValidateAppCommand;
use Shopwell\Core\Framework\App\Context\Gateway\AppContextGateway;
use Shopwell\Core\Framework\App\Context\Payload\AppContextGatewayPayloadService;
use Shopwell\Core\Framework\App\Cookie\AppCookieCollectListener;
use Shopwell\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Shopwell\Core\Framework\App\DeletedApps\RememberDeletedAppsSecretSubscriber;
use Shopwell\Core\Framework\App\Delta\AppConfirmationDeltaProvider;
use Shopwell\Core\Framework\App\Delta\DomainsDeltaProvider;
use Shopwell\Core\Framework\App\Delta\PermissionsDeltaProvider;
use Shopwell\Core\Framework\App\Feature\AppFeatureDefinitionRegistry;
use Shopwell\Core\Framework\App\Feature\AppFeatureLifecycleHandler;
use Shopwell\Core\Framework\App\Feature\AppFeatureStorage;
use Shopwell\Core\Framework\App\Flow\Action\AppFlowActionLoadedSubscriber;
use Shopwell\Core\Framework\App\Flow\Action\AppFlowActionProvider;
use Shopwell\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopwell\Core\Framework\App\Hmac\QuerySigner;
use Shopwell\Core\Framework\App\Http\AppSystemHttpMiddleware;
use Shopwell\Core\Framework\App\Lifecycle\AppFeatureValidator;
use Shopwell\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopwell\Core\Framework\App\Lifecycle\AppLifecycleIterator;
use Shopwell\Core\Framework\App\Lifecycle\AppLoader;
use Shopwell\Core\Framework\App\Lifecycle\AppManager;
use Shopwell\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopwell\Core\Framework\App\Lifecycle\Handler\ActionButtonLifecycleHandler;
use Shopwell\Core\Framework\App\Lifecycle\Handler\CmsBlockLifecycleHandler;
use Shopwell\Core\Framework\App\Lifecycle\Handler\CustomFieldLifecycleHandler;
use Shopwell\Core\Framework\App\Lifecycle\Handler\FlowActionLifecycleHandler;
use Shopwell\Core\Framework\App\Lifecycle\Handler\FlowEventLifecycleHandler;
use Shopwell\Core\Framework\App\Lifecycle\Handler\ModuleLifecycleHandler;
use Shopwell\Core\Framework\App\Lifecycle\Handler\PaymentMethodLifecycleHandler;
use Shopwell\Core\Framework\App\Lifecycle\Handler\RuleConditionLifecycleHandler;
use Shopwell\Core\Framework\App\Lifecycle\Handler\ScriptLifecycleHandler;
use Shopwell\Core\Framework\App\Lifecycle\Handler\ShippingMethodLifecycleHandler;
use Shopwell\Core\Framework\App\Lifecycle\Handler\TaxProviderLifecycleHandler;
use Shopwell\Core\Framework\App\Lifecycle\Handler\TemplateLifecycleHandler;
use Shopwell\Core\Framework\App\Lifecycle\Handler\WebhookLifecycleHandler;
use Shopwell\Core\Framework\App\Lifecycle\PermissionLifecycleService;
use Shopwell\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopwell\Core\Framework\App\Lifecycle\Registration\HandshakeFactory;
use Shopwell\Core\Framework\App\Lifecycle\ScriptFileReader;
use Shopwell\Core\Framework\App\Lifecycle\Update\AbstractAppUpdater;
use Shopwell\Core\Framework\App\Lifecycle\Update\AppUpdater;
use Shopwell\Core\Framework\App\Manifest\ManifestFactory;
use Shopwell\Core\Framework\App\Manifest\ModuleLoader;
use Shopwell\Core\Framework\App\MessageHandler\RotateAppSecretHandler;
use Shopwell\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopwell\Core\Framework\App\Payment\Handler\AppPaymentHandler;
use Shopwell\Core\Framework\App\Payment\Payload\PaymentPayloadService;
use Shopwell\Core\Framework\App\Privileges\AppCapability;
use Shopwell\Core\Framework\App\Privileges\Privileges;
use Shopwell\Core\Framework\App\ScheduledTask\DeleteCascadeAppsHandler;
use Shopwell\Core\Framework\App\ScheduledTask\DeleteCascadeAppsTask;
use Shopwell\Core\Framework\App\ScheduledTask\SystemHeartbeatHandler;
use Shopwell\Core\Framework\App\ScheduledTask\SystemHeartbeatTask;
use Shopwell\Core\Framework\App\ScheduledTask\UpdateAppsHandler;
use Shopwell\Core\Framework\App\ScheduledTask\UpdateAppsTask;
use Shopwell\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopwell\Core\Framework\App\ShopId\Fingerprint\InstallationPath;
use Shopwell\Core\Framework\App\ShopId\Fingerprint\SalesChannelDomainUrls;
use Shopwell\Core\Framework\App\ShopId\FingerprintGenerator;
use Shopwell\Core\Framework\App\ShopId\ShopIdProvider;
use Shopwell\Core\Framework\App\ShopIdChangeResolver\MoveShopPermanentlyStrategy;
use Shopwell\Core\Framework\App\ShopIdChangeResolver\ReinstallAppsStrategy;
use Shopwell\Core\Framework\App\ShopIdChangeResolver\Resolver;
use Shopwell\Core\Framework\App\ShopIdChangeResolver\UninstallAppsStrategy;
use Shopwell\Core\Framework\App\Source\Local;
use Shopwell\Core\Framework\App\Source\NoDatabaseSourceResolver;
use Shopwell\Core\Framework\App\Source\RemoteZip;
use Shopwell\Core\Framework\App\Source\SourceResolver;
use Shopwell\Core\Framework\App\Source\TemporaryDirectoryFactory;
use Shopwell\Core\Framework\App\Subscriber\AppLoadedSubscriber;
use Shopwell\Core\Framework\App\Subscriber\AppScriptConditionConstraintsSubscriber;
use Shopwell\Core\Framework\App\Subscriber\CustomFieldProtectionSubscriber;
use Shopwell\Core\Framework\App\Subscriber\DiscardUnconfirmedAppSecretsListener;
use Shopwell\Core\Framework\App\TaxProvider\Payload\TaxProviderPayloadService;
use Shopwell\Core\Framework\App\Telemetry\AppTelemetrySubscriber;
use Shopwell\Core\Framework\App\Template\TemplateDefinition;
use Shopwell\Core\Framework\App\Template\TemplateLoader;
use Shopwell\Core\Framework\App\Url\AppUrlVerificationPrinter;
use Shopwell\Core\Framework\App\Url\AppUrlVerifier;
use Shopwell\Core\Framework\App\Validation\AppNameValidator;
use Shopwell\Core\Framework\App\Validation\AppRequirementsValidator;
use Shopwell\Core\Framework\App\Validation\ConfigValidator;
use Shopwell\Core\Framework\App\Validation\HookableValidator;
use Shopwell\Core\Framework\App\Validation\ManifestValidator;
use Shopwell\Core\Framework\App\Validation\Requirements\PublicAccess;
use Shopwell\Core\Framework\App\Validation\Requirements\SecureUrlValidator;
use Shopwell\Core\Framework\App\Validation\TranslationValidator;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Gateway\Context\Command\Executor\ContextGatewayCommandExecutor;
use Shopwell\Core\Framework\Gateway\Context\Command\Registry\ContextGatewayCommandRegistry;
use Shopwell\Core\Framework\Log\ExceptionLogger;
use Shopwell\Core\Framework\Script\Execution\ScriptExecutor;
use Shopwell\Core\Framework\Store\Authentication\LocaleProvider;
use Shopwell\Core\Framework\Store\InAppPurchase;
use Shopwell\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopwell\Core\Framework\Store\Services\AbstractStoreAppLifecycleService;
use Shopwell\Core\Framework\Store\Services\ExtensionDownloader;
use Shopwell\Core\Framework\Store\Services\StoreClient;
use Shopwell\Core\Framework\Telemetry\Metrics\Meter;
use Shopwell\Core\Framework\Webhook\Authorization\Policy\PolicyRegistry;
use Shopwell\Core\Framework\Webhook\BusinessEventEncoder;
use Shopwell\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Shopwell\Core\Framework\Webhook\Validation\WebhookTargetValidator;
use Shopwell\Core\Framework\Webhook\WebhookCacheClearer;
use Shopwell\Core\System\CustomEntity\CustomEntityLifecycleService;
use Shopwell\Core\System\CustomField\CustomFieldSetPersister;
use Shopwell\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\Clock\ClockInterface as SymfonyClockInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('shopwell.app_dir', '%kernel.project_dir%/custom/apps');

    $services = $containerConfigurator->services();

    $services->set(ManifestFactory::class)
        ->args([
            service(SourceResolver::class),
        ]);

    $services->set(AppLoadedSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(CustomFieldProtectionSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AppScriptConditionConstraintsSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(ShopIdProvider::class)
        ->public()
        ->args([
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(Connection::class),
            service(FingerprintGenerator::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ModuleLoader::class)
        ->args([
            service('app.repository'),
            service(ShopIdProvider::class),
            service(QuerySigner::class),
        ]);

    $services->set(TranslationValidator::class)
        ->tag('shopwell.app_manifest.validator');

    $services->set(AppNameValidator::class)
        ->tag('shopwell.app_manifest.validator');

    $services->set(ManifestValidator::class)
        ->args([
            tagged_iterator('shopwell.app_manifest.validator'),
        ]);

    $services->set(ConfigValidator::class)
        ->args([
            service(ConfigReader::class),
        ])
        ->tag('shopwell.app_manifest.validator');

    $services->set(HookableValidator::class)
        ->args([
            service(HookableEventCollector::class),
            service(PolicyRegistry::class),
        ])
        ->tag('shopwell.app_manifest.validator');

    $services->set(SecureUrlValidator::class);

    $services->set(PublicAccess::class)
        ->args([
            service(SecureUrlValidator::class),
            service('shopwell.app_system.guzzle'),
        ])
        ->tag('app.requirements_validator')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(AppRequirementsValidator::class)
        ->args([
            tagged_iterator('app.requirements_validator'),
            service('logger'),
            param('kernel.environment'),
        ]);

    $services->set(PermissionLifecycleService::class)
        ->args([
            service(Connection::class),
            service(Privileges::class),
            service(ClockInterface::class),
        ]);

    // App Lifecycle Persisters - do not change priority without careful consideration
    $services->set(FlowActionLifecycleHandler::class)
        ->args([
            service('app_flow_action.repository'),
            service(Connection::class),
        ])
        ->tag('shopwell.app_lifecycle.handler', ['priority' => 0]);

    $services->set(WebhookLifecycleHandler::class)
        ->args([
            service(Connection::class),
            service(WebhookCacheClearer::class),
            service(ClockInterface::class),
            service(WebhookTargetValidator::class),
        ])
        ->tag('shopwell.app_lifecycle.handler', ['priority' => -100]);

    $services->set(FlowEventLifecycleHandler::class)
        ->args([
            service('app_flow_event.repository'),
            service(Connection::class),
        ])
        ->tag('shopwell.app_lifecycle.handler', ['priority' => -200]);

    $services->set(PaymentMethodLifecycleHandler::class)
        ->args([
            service('payment_method.repository'),
            service('media.repository'),
            service(MediaService::class),
        ])
        ->tag('shopwell.app_lifecycle.handler', ['priority' => -300]);

    $services->set(TaxProviderLifecycleHandler::class)
        ->args([
            service('tax_provider.repository'),
        ])
        ->tag('shopwell.app_lifecycle.handler', ['priority' => -400]);

    $services->set(ModuleLifecycleHandler::class)
        ->args([
            service('app.repository'),
        ])
        ->tag('shopwell.app_lifecycle.handler', ['priority' => -500]);

    $services->set(ShippingMethodLifecycleHandler::class)
        ->args([
            service('shipping_method.repository'),
            service('app_shipping_method.repository'),
            service('media.repository'),
            service(MediaService::class),
        ])
        ->tag('shopwell.app_lifecycle.handler', ['priority' => -600]);

    $services->set(RuleConditionLifecycleHandler::class)
        ->args([
            service(ScriptFileReader::class),
            service('app_script_condition.repository'),
            service('app.repository'),
        ])
        ->tag('shopwell.app_lifecycle.handler', ['priority' => -700]);

    $services->set(ActionButtonLifecycleHandler::class)
        ->args([
            service('app_action_button.repository'),
        ])
        ->tag('shopwell.app_lifecycle.handler', ['priority' => -800]);

    $services->set(TemplateLifecycleHandler::class)
        ->args([
            service(TemplateLoader::class),
            service('app_template.repository'),
            service('app.repository'),
            service(CacheClearer::class),
        ])
        ->tag('shopwell.app_lifecycle.handler', ['priority' => -900]);

    $services->set(ScriptLifecycleHandler::class)
        ->args([
            service(ScriptFileReader::class),
            service('script.repository'),
            service('app.repository'),
        ])
        ->tag('shopwell.app_lifecycle.handler', ['priority' => -1000]);

    $services->set(CustomFieldLifecycleHandler::class)
        ->args([
            service(CustomFieldSetPersister::class),
        ])
        ->tag('shopwell.app_lifecycle.handler', ['priority' => -1100]);

    $services->set(CmsBlockLifecycleHandler::class)
        ->args([
            service('app_cms_block.repository'),
            service(BlockTemplateLoader::class),
        ])
        ->tag('shopwell.app_lifecycle.handler', ['priority' => -1200]);

    $services->set(AppFeatureLifecycleHandler::class)
        ->args([
            service(AppFeatureDefinitionRegistry::class),
            service(AppFeatureStorage::class),
        ])
        ->tag('shopwell.app_lifecycle.handler', ['priority' => -1300]);

    $services->set(AppFeatureDefinitionRegistry::class)
        ->args([
            tagged_iterator('shopwell.app_feature.definition'),
        ]);

    $services->set(AppFeatureStorage::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
            service(AppFeatureDefinitionRegistry::class),
        ]);

    $services->set(ScriptFileReader::class)
        ->args([
            service(SourceResolver::class),
        ]);

    $services->set(TemplateLoader::class)
        ->args([
            service(SourceResolver::class),
        ]);

    $services->set(AppService::class)
        ->args([
            service(AppLifecycleIterator::class),
            service(AppLifecycle::class),
        ]);

    $services->set(AppPayloadServiceHelper::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(JsonEntityEncoder::class),
            service(ShopIdProvider::class),
            service(InAppPurchase::class),
            env('APP_URL'),
            service(SymfonyClockInterface::class),
        ]);

    $services->set(ActiveAppsLoader::class)
        ->args([
            service(Connection::class),
            service(AppLoader::class),
            param('kernel.project_dir'),
        ])
        ->tag('kernel.reset', ['method' => 'reset'])
        ->tag('kernel.event_listener', ['event' => 'console.terminate', 'method' => 'reset']);

    $services->set(PaymentPayloadService::class)
        ->args([
            service(AppPayloadServiceHelper::class),
            service('shopwell.app_system.guzzle'),
        ]);

    $services->set(TaxProviderPayloadService::class)
        ->args([
            service(AppPayloadServiceHelper::class),
            service('shopwell.app_system.guzzle'),
            service(ExceptionLogger::class),
        ]);

    $services->set(AppCheckoutGatewayPayloadService::class)
        ->args([
            service(AppPayloadServiceHelper::class),
            service('shopwell.app_system.guzzle'),
            service(ExceptionLogger::class),
        ]);

    $services->set(AppContextGatewayPayloadService::class)
        ->args([
            service(AppPayloadServiceHelper::class),
            service('shopwell.app_system.guzzle'),
        ]);

    $services->set(AppCheckoutGateway::class)
        ->args([
            service(AppCheckoutGatewayPayloadService::class),
            service(CheckoutGatewayCommandExecutor::class),
            service(CheckoutGatewayCommandRegistry::class),
            service('app.repository'),
            service('event_dispatcher'),
            service(ExceptionLogger::class),
            service(ActiveAppsLoader::class),
            service(AppCapability::class),
        ]);

    $services->set(AppContextGateway::class)
        ->args([
            service(AppContextGatewayPayloadService::class),
            service(ContextGatewayCommandExecutor::class),
            service(ContextGatewayCommandRegistry::class),
            service('app.repository'),
            service('event_dispatcher'),
            service(ExceptionLogger::class),
            service(AppCapability::class),
        ]);

    $services->set(AppCookieCollectListener::class)
        ->args([
            service('app.repository'),
        ])
        ->tag('kernel.event_listener');

    $services->set(AppPaymentHandler::class)
        ->args([
            service(StateMachineRegistry::class),
            service(PaymentPayloadService::class),
            service('order_transaction_capture_refund.repository'),
            service('order_transaction.repository'),
            service('app.repository'),
            service(Connection::class),
        ])
        ->tag('shopwell.payment.method');

    $services->set(AppRegistrationService::class)
        ->args([
            service(HandshakeFactory::class),
            service('shopwell.app_system.guzzle'),
            service('app.repository'),
            env('APP_URL'),
            service(ShopIdProvider::class),
            param('kernel.shopwell_version'),
            service(ClockInterface::class),
            service('logger'),
        ]);

    $services->set(AppSecretRotationService::class)
        ->args([
            service(AppRegistrationService::class),
            service('app.repository'),
            service('integration.repository'),
            service('messenger.default_bus'),
            service('logger'),
            service(ManifestFactory::class),
            service(ClockInterface::class),
            service(DeletedAppsGateway::class),
        ]);

    $services->set(AppFeatureValidator::class)
        ->args([
            param('kernel.environment'),
        ]);

    $services->set(AppStorage::class)
        ->args([
            service('app.repository'),
        ]);

    $services->set(HandshakeFactory::class)
        ->args([
            env('APP_URL'),
            service(ShopIdProvider::class),
            service(StoreClient::class),
            param('kernel.shopwell_version'),
            service(ClockInterface::class),
        ]);

    $services->set(AppManager::class)
        ->args([
            tagged_iterator('shopwell.app_lifecycle.handler'),
            service('app.repository'),
            service(PermissionLifecycleService::class),
            service('event_dispatcher'),
            service(AppRegistrationService::class),
            service(AppSecretRotationService::class),
            service(ManifestFactory::class),
            service(ActiveAppsLoader::class),
            service('language.repository'),
            service(SystemConfigService::class),
            service(ConfigValidator::class),
            service('integration.repository'),
            service('acl_role.repository'),
            service(AssetService::class),
            service(ScriptExecutor::class),
            param('kernel.project_dir'),
            service(CustomEntityLifecycleService::class),
            param('kernel.shopwell_version'),
            service(AppFeatureValidator::class),
            service(SourceResolver::class),
            service(ConfigReader::class),
            service(DeletedAppsGateway::class),
            service(AppRequirementsValidator::class),
            service(ClockInterface::class),
        ]);

    $services->set(DiscardUnconfirmedAppSecretsListener::class)
        ->args([
            service('app.repository'),
        ])
        ->tag('kernel.event_listener');

    $services->set(AppLifecycle::class)
        ->args([
            service(AppManager::class),
            service(AppStorage::class),
        ]);

    $services->set(AppLifecycleIterator::class)
        ->args([
            service('app.repository'),
            service(AppLoader::class),
        ]);

    $services->set(AbstractAppUpdater::class, AppUpdater::class)
        ->args([
            service(AbstractExtensionDataProvider::class),
            service('app.repository'),
            service(ExtensionDownloader::class),
            service(AbstractStoreAppLifecycleService::class),
        ]);

    $services->set(UpdateAppsTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(UpdateAppsHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(AbstractAppUpdater::class)->nullOnInvalid(),
        ])
        ->tag('messenger.message_handler');

    $services->set(DeleteCascadeAppsTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(DeleteCascadeAppsHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service('acl_role.repository'),
            service('integration.repository'),
            service(ClockInterface::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(RotateAppSecretHandler::class)
        ->args([
            service(AppSecretRotationService::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(AppLoader::class)
        ->args([
            param('shopwell.app_dir'),
            service('logger'),
        ]);

    $services->set('shopwell.app_system.guzzle.middleware', AuthMiddleware::class)
        ->args([
            param('kernel.shopwell_version'),
            service(AppLocaleProvider::class),
        ]);

    $services->set('shopwell.app_system.trusted_url_resolver', TrustedUrlResolver::class)
        ->args([
            null,
            true,
            param('shopwell.app_system.allowed_private_ip_addresses'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set('shopwell.app_system.guzzle.security_middleware', AppSystemHttpMiddleware::class)
        ->args([
            service('shopwell.app_system.trusted_url_resolver'),
            param('shopwell.app_system.allow_unencrypted_traffic'),
        ])
        ->arg('$enableUrlValidation', param('shopwell.app_system.enable_url_validation'));

    $services->set('shopwell.app_system.guzzle', Client::class)
        ->lazy()
        ->args([
            [
                'timeout' => 5,
                'connect_timeout' => 1,
                'proxy' => [],
                'allow_redirects' => AuthMiddleware::ALLOW_REDIRECTS,
                'handler' => inline_service(HandlerStack::class)
                    ->factory([HandlerStack::class, 'create'])
                    ->call('after', [
                        'allow_redirects',
                        service('shopwell.app_system.guzzle.security_middleware'),
                        'app_system_http_security',
                    ])
                    ->call('push', [
                        service('shopwell.app_system.guzzle.middleware'),
                    ]),
            ],
        ]);

    $services->set(ActionButtonLoader::class)
        ->args([
            service('app_action_button.repository'),
        ]);

    $services->set(ActionButtonResponseFactory::class)
        ->args([
            tagged_iterator('shopwell.action_button.response_factory'),
        ]);

    $services->set(NotificationResponseFactory::class)
        ->tag('shopwell.action_button.response_factory');

    $services->set(OpenModalResponseFactory::class)
        ->args([
            service(QuerySigner::class),
        ])
        ->tag('shopwell.action_button.response_factory');

    $services->set(OpenNewTabResponseFactory::class)
        ->args([
            service(QuerySigner::class),
        ])
        ->tag('shopwell.action_button.response_factory');

    $services->set(ReloadDataResponseFactory::class)
        ->tag('shopwell.action_button.response_factory');

    $services->set(QuerySigner::class)
        ->args([
            env('APP_URL'),
            param('kernel.shopwell_version'),
            service(LocaleProvider::class),
            service(ShopIdProvider::class),
            service(InAppPurchase::class),
            service(ClockInterface::class),
        ]);

    $services->set(Executor::class)
        ->args([
            service('shopwell.app_system.guzzle'),
            service('logger'),
            service(ActionButtonResponseFactory::class),
            service(ShopIdProvider::class),
            service('router'),
            service('request_stack'),
            service('kernel'),
            service(ClockInterface::class),
        ]);

    $services->set(AppActionLoader::class)
        ->args([
            service('app_action_button.repository'),
            service(AppPayloadServiceHelper::class),
        ]);

    $services->set(AppActionController::class)
        ->public()
        ->args([
            service(ActionButtonLoader::class),
            service(AppActionLoader::class),
            service(Executor::class),
            service(ModuleLoader::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(AppCmsController::class)
        ->public()
        ->args([
            service('app_cms_block.repository'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(AppJWTGenerateRoute::class)
        ->public()
        ->args([
            service(Connection::class),
            service(ShopIdProvider::class),
            service(InAppPurchase::class),
            service(ClockInterface::class),
        ]);

    $services->set(AppSecretRotationController::class)
        ->public()
        ->args([
            service('app.repository'),
            service(AppSecretRotationService::class),
        ]);

    $services->set(AppPrinter::class)
        ->args([
            service('app.repository'),
        ]);

    $services->set(AppLocaleProvider::class)
        ->public()
        ->args([
            service('user.repository'),
            service(LanguageLocaleCodeProvider::class),
        ]);

    // COMMANDS
    $services->set(RefreshAppCommand::class)
        ->args([
            service(AppService::class),
            service(AppPrinter::class),
            service(ManifestValidator::class),
        ])
        ->tag('console.command');

    $services->set(InstallAppCommand::class)
        ->args([
            service(AppLoader::class),
            service(AppLifecycle::class),
            service(AppPrinter::class),
            service(ManifestValidator::class),
        ])
        ->tag('console.command');

    $services->set(UninstallAppCommand::class)
        ->args([
            service(AppLifecycle::class),
            service(AppStorage::class),
        ])
        ->tag('console.command');

    $services->set(ActivateAppCommand::class)
        ->args([
            service(AppStorage::class),
            service(AppLifecycle::class),
        ])
        ->tag('console.command');

    $services->set(DeactivateAppCommand::class)
        ->args([
            service(AppStorage::class),
            service(AppLifecycle::class),
        ])
        ->tag('console.command');

    $services->set(CreateAppCommand::class)
        ->args([
            service(AppLifecycle::class),
            param('shopwell.app_dir'),
        ])
        ->tag('console.command');

    $services->set(ValidateAppCommand::class)
        ->args([
            param('shopwell.app_dir'),
            service(ManifestValidator::class),
        ])
        ->tag('console.command');

    $services->set(ChangeShopIdCommand::class)
        ->args([
            service(Resolver::class),
        ])
        ->tag('console.command');

    $services->set(AppListCommand::class)
        ->args([
            service(AppStorage::class),
        ])
        ->tag('console.command');

    $services->set(RotateAppSecretCommand::class)
        ->args([
            service('app.repository'),
            service(AppSecretRotationService::class),
            service(ActiveAppsLoader::class),
        ])
        ->tag('console.command');

    $services->set(ShopIdController::class)
        ->public()
        ->args([
            service(Resolver::class),
            service(ShopIdProvider::class),
            service('app.repository'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(Resolver::class)
        ->public()
        ->args([
            tagged_iterator('shopwell.app_url_changed_resolver'),
        ]);

    $services->set(MoveShopPermanentlyStrategy::class)
        ->args([
            service('app.repository'),
            service(AppManager::class),
            service(ShopIdProvider::class),
            service('logger'),
        ])
        ->tag('shopwell.app_url_changed_resolver', ['priority' => -100]);

    $services->set(ReinstallAppsStrategy::class)
        ->args([
            service('app.repository'),
            service(AppManager::class),
            service(ShopIdProvider::class),
            service('logger'),
        ])
        ->tag('shopwell.app_url_changed_resolver', ['priority' => 100]);

    $services->set(UninstallAppsStrategy::class)
        ->args([
            service('app.repository'),
            service(ShopIdProvider::class),
            service(AppManager::class),
        ])
        ->tag('shopwell.app_url_changed_resolver', ['priority' => 0]);

    // DELTA
    $services->set(PermissionsDeltaProvider::class)
        ->tag('shopwell.app_delta');

    $services->set(DomainsDeltaProvider::class)
        ->tag('shopwell.app_delta');

    // ENTITY DEFINITIONS
    $services->set(AppDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AppTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ActionButtonDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(ActionButtonTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(TemplateDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AppPaymentMethodDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AppScriptConditionDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AppScriptConditionTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AppCmsBlockDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AppCmsBlockTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AppFlowActionDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AppFlowActionTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AppFlowEventDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AppShippingMethodDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AppFlowActionLoadedSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(BlockTemplateLoader::class);

    $services->set(AppFlowActionProvider::class)
        ->public()
        ->args([
            service(Connection::class),
            service(BusinessEventEncoder::class),
            service(StringTemplateRenderer::class),
        ]);

    $services->set(AppConfirmationDeltaProvider::class)
        ->args([
            tagged_iterator('shopwell.app_delta'),
        ]);

    $services->set(NoDatabaseSourceResolver::class)
        ->args([
            service(ActiveAppsLoader::class),
        ]);

    $services->set(SourceResolver::class)
        ->args([
            tagged_iterator('app.source_resolver'),
            service('app.repository'),
            service(NoDatabaseSourceResolver::class),
        ]);

    $services->set(RemoteZip::class)
        ->args([
            service(TemporaryDirectoryFactory::class),
            service(AppDownloader::class),
            service(AppExtractor::class),
        ])
        ->tag('app.source_resolver');

    $services->set(Local::class)
        ->args([
            param('kernel.project_dir'),
        ])
        ->tag('app.source_resolver', ['priority' => -100]);

    $services->set(AppArchiveValidator::class);

    $services->set(AppExtractor::class)
        ->args([
            service(AppArchiveValidator::class),
        ]);

    $services->set(AppDownloader::class)
        ->args([
            service(HttpClientInterface::class),
        ]);

    $services->set(TemporaryDirectoryFactory::class);

    $services->set(AppTelemetrySubscriber::class)
        ->args([
            service(Meter::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('shopwell.telemetry.subscriber');

    $services->set(Privileges::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
        ]);

    $services->set(AppCapability::class)
        ->args([
            service(Privileges::class),
        ]);

    $services->set(AppPrivilegeController::class)
        ->public()
        ->args([
            service(Connection::class),
            service(Privileges::class),
        ]);

    $services->set(SalesChannelDomainUrls::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopwell.app_system.shop_id_fingerprint');

    $services->set(InstallationPath::class)
        ->args([
            param('kernel.project_dir'),
        ])
        ->tag('shopwell.app_system.shop_id_fingerprint');

    $services->set(AppUrl::class)
        ->tag('shopwell.app_system.shop_id_fingerprint');

    $services->set(FingerprintGenerator::class)
        ->args([
            tagged_iterator('shopwell.app_system.shop_id_fingerprint'),
        ]);

    $services->set(CheckShopIdCommand::class)
        ->args([
            service(SystemConfigService::class),
            service(FingerprintGenerator::class),
        ])
        ->tag('console.command');

    $services->set(SystemHeartbeatTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(SystemHeartbeatHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service('event_dispatcher'),
        ])
        ->tag('messenger.message_handler');

    $services->set(DeletedAppsGateway::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(AppSecretResolver::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(RememberDeletedAppsSecretSubscriber::class)
        ->args([
            service('app.repository'),
            service(DeletedAppsGateway::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AppUrlVerifier::class)
        ->args([
            param('kernel.environment'),
            param('kernel.shopwell_version'),
            service('cache.app'),
            service(HttpClientInterface::class),
            service('lock.factory'),
            service('logger'),
            service(ClockInterface::class),
        ]);

    $services->set(AppUrlVerificationPrinter::class)
        ->args([
            service(ShopIdProvider::class),
        ]);

    $services->set(AppUrlVerificationStatusCommand::class)
        ->args([
            service(AppUrlVerifier::class),
            service(AppUrlVerificationPrinter::class),
        ])
        ->tag('console.command');

    $services->set(AppUrlVerifyCommand::class)
        ->args([
            service(ShopIdProvider::class),
            service(AppUrlVerifier::class),
            service(AppUrlVerificationPrinter::class),
        ])
        ->tag('console.command');

    $services->set(VerifyShopController::class)
        ->public()
        ->args([
            service('shopwell.rate_limiter'),
            service(AppUrlVerifier::class),
        ]);
};
