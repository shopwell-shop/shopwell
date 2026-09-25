<?php declare(strict_types=1);

namespace Shopwell\Core\Maintenance\DependencyInjection;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Adapter\Cache\CacheClearer;
use Shopwell\Core\Framework\App\ShopId\ShopIdProvider;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopwell\Core\Framework\Store\Services\ExtensionLifecycleService;
use Shopwell\Core\Installer\Finish\SystemLocker;
use Shopwell\Core\Maintenance\SalesChannel\Command\SalesChannelCreateCommand;
use Shopwell\Core\Maintenance\SalesChannel\Command\SalesChannelListCommand;
use Shopwell\Core\Maintenance\SalesChannel\Command\SalesChannelMaintenanceDisableCommand;
use Shopwell\Core\Maintenance\SalesChannel\Command\SalesChannelMaintenanceEnableCommand;
use Shopwell\Core\Maintenance\SalesChannel\Command\SalesChannelReplaceUrlCommand;
use Shopwell\Core\Maintenance\SalesChannel\Command\SalesChannelUpdateDomainCommand;
use Shopwell\Core\Maintenance\SalesChannel\Service\SalesChannelCreator;
use Shopwell\Core\Maintenance\Staging\Command\SystemSetupStagingCommand;
use Shopwell\Core\Maintenance\Staging\Handler\StagingAppHandler;
use Shopwell\Core\Maintenance\Staging\Handler\StagingExtensionHandler;
use Shopwell\Core\Maintenance\Staging\Handler\StagingMailHandler;
use Shopwell\Core\Maintenance\Staging\Handler\StagingSalesChannelHandler;
use Shopwell\Core\Maintenance\Staging\Handler\StagingSystemConfigHandler;
use Shopwell\Core\Maintenance\System\Command\SystemConfigureShopCommand;
use Shopwell\Core\Maintenance\System\Command\SystemGenerateAppSecretCommand;
use Shopwell\Core\Maintenance\System\Command\SystemInstallCommand;
use Shopwell\Core\Maintenance\System\Command\SystemIsInstalledCommand;
use Shopwell\Core\Maintenance\System\Command\SystemSetupCommand;
use Shopwell\Core\Maintenance\System\Command\SystemUpdateFinishCommand;
use Shopwell\Core\Maintenance\System\Command\SystemUpdatePrepareCommand;
use Shopwell\Core\Maintenance\System\Service\AppUrlVerifier;
use Shopwell\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Shopwell\Core\Maintenance\System\Service\SetupDatabaseAdapter;
use Shopwell\Core\Maintenance\System\Service\ShopConfigurator;
use Shopwell\Core\Maintenance\User\Command\UserChangePasswordCommand;
use Shopwell\Core\Maintenance\User\Command\UserCreateCommand;
use Shopwell\Core\Maintenance\User\Command\UserListCommand;
use Shopwell\Core\Maintenance\User\Service\UserProvisioner;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Dotenv\Command\DotenvDumpCommand;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('env(APP_URL_CHECK_DISABLED)', 'false');

    $services = $containerConfigurator->services();

    $services->set(DatabaseConnectionFactory::class);

    $services->set(SystemInstallCommand::class)
        ->args([
            param('kernel.project_dir'),
            service(SetupDatabaseAdapter::class),
            service(DatabaseConnectionFactory::class),
            service(CacheClearer::class),
            service(SystemLocker::class),
            service(ClockInterface::class),
            service(EventDispatcherInterface::class),
        ])
        ->tag('console.command');

    $services->set(SystemIsInstalledCommand::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('console.command');

    $services->set(SystemGenerateAppSecretCommand::class)
        ->tag('console.command');

    $services->set(SystemSetupCommand::class)
        ->args([
            param('kernel.project_dir'),
            service(DotenvDumpCommand::class),
        ])
        ->tag('console.command');

    $services->set(DotenvDumpCommand::class)
        ->args([
            param('kernel.project_dir'),
        ])
        ->tag('console.command');

    $services->set(SystemUpdatePrepareCommand::class)
        ->args([
            service('service_container'),
            param('kernel.shopware_version'),
        ])
        ->tag('console.command');

    $services->set(SystemUpdateFinishCommand::class)
        ->args([
            service('event_dispatcher'),
            service(SystemConfigService::class),
            param('kernel.shopware_version'),
        ])
        ->tag('console.command');

    $services->set(SalesChannelUpdateDomainCommand::class)
        ->args([
            service('sales_channel_domain.repository'),
        ])
        ->tag('console.command');

    $services->set(SalesChannelReplaceUrlCommand::class)
        ->args([
            service('sales_channel_domain.repository'),
        ])
        ->tag('console.command');

    $services->set(SystemConfigureShopCommand::class)
        ->args([
            service(ShopConfigurator::class),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(AppUrlVerifier::class)
        ->args([
            service('shopware.maintenance.client'),
            service(Connection::class),
            param('kernel.environment'),
            env('APP_URL_CHECK_DISABLED')->bool(),
        ]);

    $services->set('shopware.maintenance.client', Client::class);

    $services->set(ShopConfigurator::class)
        ->args([
            service(Connection::class),
            service(EventDispatcherInterface::class),
            service(ClockInterface::class),
        ]);

    $services->set(SalesChannelCreateCommand::class)
        ->args([
            service(SalesChannelCreator::class),
        ])
        ->tag('console.command');

    $services->set(SalesChannelCreator::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('sales_channel.repository'),
            service('payment_method.repository'),
            service('shipping_method.repository'),
            service('country.repository'),
            service('category.repository'),
        ]);

    $services->set(SalesChannelListCommand::class)
        ->args([
            service('sales_channel.repository'),
        ])
        ->tag('console.command');

    $services->set(SalesChannelMaintenanceEnableCommand::class)
        ->args([
            service('sales_channel.repository'),
        ])
        ->tag('console.command');

    $services->set(SalesChannelMaintenanceDisableCommand::class)
        ->args([
            service('sales_channel.repository'),
        ])
        ->tag('console.command');

    $services->set(UserCreateCommand::class)
        ->args([
            service(UserProvisioner::class),
        ])
        ->tag('console.command');

    $services->set(UserChangePasswordCommand::class)
        ->args([
            service('user.repository'),
        ])
        ->tag('console.command');

    $services->set(UserListCommand::class)
        ->args([
            service('user.repository'),
        ])
        ->tag('console.command');

    $services->set(UserProvisioner::class)
        ->public()
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
        ]);

    $services->set(SetupDatabaseAdapter::class);

    $services->set(SystemLocker::class)
        ->args([
            param('kernel.project_dir'),
        ]);

    $services->set(SystemSetupStagingCommand::class)
        ->args([
            service('event_dispatcher'),
            service(SystemConfigService::class),
            param('shopware.staging.mailing.disable_delivery'),
            param('shopware.staging.sales_channel.domain_rewrite'),
            param('shopware.staging.extensions.disable'),
            param('shopware.staging.system_config'),
        ])
        ->tag('console.command');

    $services->set(StagingAppHandler::class)
        ->args([
            service(Connection::class),
            service(ShopIdProvider::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(StagingMailHandler::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(StagingSalesChannelHandler::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(StagingExtensionHandler::class)
        ->args([
            service(KernelInterface::class),
            service(AbstractExtensionDataProvider::class),
            service(ExtensionLifecycleService::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(StagingSystemConfigHandler::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('kernel.event_listener');
};
