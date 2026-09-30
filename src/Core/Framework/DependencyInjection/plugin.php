<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection;

use Composer\Autoload\ClassLoader;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Adapter\Asset\AssetService;
use Shopwell\Core\Framework\Adapter\Cache\CacheClearer;
use Shopwell\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopwell\Core\Framework\App\ActiveAppsLoader;
use Shopwell\Core\Framework\App\Source\SourceResolver;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Migration\MigrationCollectionLoader;
use Shopwell\Core\Framework\Plugin\Aggregate\PluginTranslation\PluginTranslationDefinition;
use Shopwell\Core\Framework\Plugin\BundleConfigGenerator;
use Shopwell\Core\Framework\Plugin\Command\BundleDumpCommand;
use Shopwell\Core\Framework\Plugin\Command\Lifecycle\PluginActivateCommand;
use Shopwell\Core\Framework\Plugin\Command\Lifecycle\PluginDeactivateCommand;
use Shopwell\Core\Framework\Plugin\Command\Lifecycle\PluginInstallCommand;
use Shopwell\Core\Framework\Plugin\Command\Lifecycle\PluginUninstallCommand;
use Shopwell\Core\Framework\Plugin\Command\Lifecycle\PluginUpdateAllCommand;
use Shopwell\Core\Framework\Plugin\Command\Lifecycle\PluginUpdateCommand;
use Shopwell\Core\Framework\Plugin\Command\MakerCommand;
use Shopwell\Core\Framework\Plugin\Command\PluginCreateCommand;
use Shopwell\Core\Framework\Plugin\Command\PluginListCommand;
use Shopwell\Core\Framework\Plugin\Command\PluginRefreshCommand;
use Shopwell\Core\Framework\Plugin\Command\PluginZipImportCommand;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\Generator\AdminModuleGenerator;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\Generator\CommandGenerator;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\Generator\ComposerGenerator;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\Generator\ConfigGenerator;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\Generator\CustomFieldsetGenerator;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\Generator\EntityGenerator;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\Generator\EventSubscriberGenerator;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\Generator\GitignoreGenerator;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\Generator\JavascriptPluginGenerator;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\Generator\PluginClassGenerator;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\Generator\ScheduledTaskGenerator;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\Generator\StoreApiRouteGenerator;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\Generator\StorefrontControllerGenerator;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\Generator\TestsGenerator;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingCollector;
use Shopwell\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingWriter;
use Shopwell\Core\Framework\Plugin\Composer\CommandExecutor;
use Shopwell\Core\Framework\Plugin\Composer\PackageProvider;
use Shopwell\Core\Framework\Plugin\ExtensionExtractor;
use Shopwell\Core\Framework\Plugin\KernelPluginCollection;
use Shopwell\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Shopwell\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopwell\Core\Framework\Plugin\PluginDefinition;
use Shopwell\Core\Framework\Plugin\PluginLifecycleService;
use Shopwell\Core\Framework\Plugin\PluginManagementService;
use Shopwell\Core\Framework\Plugin\PluginService;
use Shopwell\Core\Framework\Plugin\PluginZipDetector;
use Shopwell\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Shopwell\Core\Framework\Plugin\Subscriber\PluginAclPrivilegesSubscriber;
use Shopwell\Core\Framework\Plugin\Subscriber\PluginLoadedSubscriber;
use Shopwell\Core\Framework\Plugin\Telemetry\PluginTelemetrySubscriber;
use Shopwell\Core\Framework\Plugin\Util\PluginFinder;
use Shopwell\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopwell\Core\Framework\Plugin\Util\VersionSanitizer;
use Shopwell\Core\Framework\Telemetry\Metrics\Meter;
use Shopwell\Core\System\CustomEntity\Schema\CustomEntityPersister;
use Shopwell\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopwell\Core\System\CustomField\CustomFieldSetPersister;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set('maker.auto_command.abstract', MakerCommand::class)
        ->abstract()
        ->args([
            '', // maker
            service(ScaffoldingCollector::class),
            service(ScaffoldingWriter::class),
            service(PluginService::class),
        ]);

    $services->set(KernelPluginLoader::class)
        ->public()
        ->factory([service('kernel'), 'getPluginLoader']);

    $services->set(ClassLoader::class)
        ->factory([service(KernelPluginLoader::class), 'getClassLoader']);

    $services->set(KernelPluginCollection::class)
        ->public()
        ->factory([service(KernelPluginLoader::class), 'getPluginInstances']);

    $services->set(BundleDumpCommand::class)
        ->args([
            service(BundleConfigGenerator::class),
            param('kernel.project_dir'),
        ])
        ->tag('console.command');

    $services->set(BundleConfigGenerator::class)
        ->args([
            service('kernel'),
            service(ActiveAppsLoader::class),
            tagged_iterator('shopwell.bundle_config.style_file_resolver'),
        ]);

    $services->set(PluginDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(PluginTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(PluginService::class)
        ->args([
            param('kernel.plugin_dir'),
            param('kernel.project_dir'),
            service('plugin.repository'),
            service('language.repository'),
            service(PluginFinder::class),
            service(VersionSanitizer::class),
        ]);

    $services->set(PluginLifecycleService::class)
        ->args([
            service('plugin.repository'),
            service('event_dispatcher'),
            service(KernelPluginCollection::class),
            service('service_container'),
            service(MigrationCollectionLoader::class),
            service(AssetService::class),
            service(CommandExecutor::class),
            service(RequirementsValidator::class),
            service('cache.messenger.restart_workers_signal'),
            param('kernel.shopwell_version'),
            service(SystemConfigService::class),
            service(CustomEntityPersister::class),
            service(CustomEntitySchemaUpdater::class),
            service(PluginService::class),
            service(VersionSanitizer::class),
            service(DefinitionInstanceRegistry::class),
            service(RequestStack::class),
            service(CustomFieldSetPersister::class),
            service(ClockInterface::class),
        ]);

    $services->set(PluginManagementService::class)
        ->args([
            param('kernel.project_dir'),
            service(PluginZipDetector::class),
            service(ExtensionExtractor::class),
            service(PluginService::class),
            service(Filesystem::class),
            service(CacheClearer::class),
            service('shopwell.store_download_client'),
        ]);

    $services->set(ExtensionExtractor::class)
        ->args([
            [
                'plugin' => param('kernel.plugin_dir'),
                'app' => param('kernel.app_dir'),
            ],
            service(Filesystem::class),
        ]);

    $services->set(PluginZipDetector::class);

    $services->set(ComposerPluginLoader::class)
        ->args([
            service(ClassLoader::class),
        ]);

    // Commands
    $services->set(PluginRefreshCommand::class)
        ->args([
            service(PluginService::class),
        ])
        ->tag('console.command');

    $services->set(PluginListCommand::class)
        ->args([
            service('plugin.repository'),
            service(ComposerPluginLoader::class),
        ])
        ->tag('console.command');

    $services->set(PluginZipImportCommand::class)
        ->args([
            service(PluginManagementService::class),
            service(PluginService::class),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(PluginInstallCommand::class)
        ->args([
            service(PluginLifecycleService::class),
            service('plugin.repository'),
            service(CacheClearer::class),
            param('kernel.project_dir'),
        ])
        ->tag('console.command');

    $services->set(PluginActivateCommand::class)
        ->args([
            service(PluginLifecycleService::class),
            service('plugin.repository'),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(PluginUpdateCommand::class)
        ->args([
            service(PluginLifecycleService::class),
            service('plugin.repository'),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(PluginDeactivateCommand::class)
        ->args([
            service(PluginLifecycleService::class),
            service('plugin.repository'),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(PluginUninstallCommand::class)
        ->args([
            service(PluginLifecycleService::class),
            service('plugin.repository'),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(PluginUpdateAllCommand::class)
        ->args([
            service(PluginService::class),
            service('plugin.repository'),
            service(PluginLifecycleService::class),
        ])
        ->tag('console.command');

    $services->set(PluginLoadedSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(PluginAclPrivilegesSubscriber::class)
        ->args([
            service(KernelPluginCollection::class),
        ])
        ->tag('kernel.event_subscriber');

    // Composer
    $services->set(PackageProvider::class);

    $services->set(CommandExecutor::class)
        ->lazy()
        ->args([
            param('kernel.project_dir'),
        ]);

    // Helper
    $services->set(PluginIdProvider::class)
        ->public()
        ->args([
            service('plugin.repository'),
        ]);

    $services->set(AssetService::class)
        ->args([
            service('shopwell.filesystem.asset'),
            service('shopwell.filesystem.private'),
            service('kernel'),
            service(KernelPluginLoader::class),
            service(CacheInvalidator::class),
            service(SourceResolver::class),
            service('parameter_bag'),
            service('event_dispatcher'),
        ]);

    // @deprecated tag:v6.8.0 Will be removed
    $services->alias(
        'Shopwell\Core\Framework\Plugin\Util\AssetService',
        AssetService::class,
    )->deprecate('shopwell/core', '6.7.15.0', 'The "%alias_id%" service alias is deprecated and will be removed in v6.8.0. Use Shopwell\Core\Framework\Adapter\Asset\AssetService instead.');

    // Requirement
    $services->set(RequirementsValidator::class)
        ->args([
            service('plugin.repository'),
            param('kernel.project_dir'),
        ]);

    $services->set(PluginFinder::class)
        ->args([
            service(PackageProvider::class),
        ]);

    $services->set(VersionSanitizer::class);

    $services->set(PluginCreateCommand::class)
        ->args([
            param('kernel.project_dir'),
            service(ScaffoldingCollector::class),
            service(ScaffoldingWriter::class),
            service(Filesystem::class),
            tagged_iterator('shopwell.scaffold.generator'),
        ])
        ->tag('console.command');

    $services->set(ScaffoldingCollector::class)
        ->args([
            tagged_iterator('shopwell.scaffold.generator'),
        ]);

    $services->set(ScaffoldingWriter::class)
        ->args([
            service(Filesystem::class),
        ]);

    $services->set(ComposerGenerator::class)
        ->tag('shopwell.scaffold.generator');

    $services->set(PluginClassGenerator::class)
        ->tag('shopwell.scaffold.generator');

    $services->set(TestsGenerator::class)
        ->tag('shopwell.scaffold.generator');

    $services->set(CommandGenerator::class)
        ->tag('shopwell.scaffold.generator');

    $services->set(ScheduledTaskGenerator::class)
        ->tag('shopwell.scaffold.generator');

    $services->set(EventSubscriberGenerator::class)
        ->tag('shopwell.scaffold.generator');

    $services->set(StorefrontControllerGenerator::class)
        ->tag('shopwell.scaffold.generator');

    $services->set(StoreApiRouteGenerator::class)
        ->tag('shopwell.scaffold.generator');

    $services->set(EntityGenerator::class)
        ->args([
            service(ClockInterface::class),
        ])
        ->tag('shopwell.scaffold.generator');

    $services->set(ConfigGenerator::class)
        ->tag('shopwell.scaffold.generator');

    $services->set(JavascriptPluginGenerator::class)
        ->tag('shopwell.scaffold.generator');

    $services->set(AdminModuleGenerator::class)
        ->tag('shopwell.scaffold.generator');

    $services->set(CustomFieldsetGenerator::class)
        ->tag('shopwell.scaffold.generator');

    $services->set(GitignoreGenerator::class)
        ->tag('shopwell.scaffold.generator');

    $services->set(PluginTelemetrySubscriber::class)
        ->args([
            service(Meter::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('shopwell.telemetry.subscriber');
};
