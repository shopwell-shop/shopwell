<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopwell\Core\Framework\Api\Acl\AclRoleLoader;
use Shopwell\Core\System\AdminMenu\AdminMenuItemDefinition;
use Shopwell\Core\System\AdminMenu\AdminMenuRouteRegistry;
use Shopwell\Core\System\AdminMenu\AdminMenuValidator;
use Shopwell\Core\System\AdminMenu\Aggregate\AdminMenuItemRole\AdminMenuItemRoleDefinition;
use Shopwell\Core\System\AdminMenu\Aggregate\AdminMenuItemTranslation\AdminMenuItemTranslationDefinition;
use Shopwell\Core\System\AdminMenu\Api\AdminMenuController;
use Shopwell\Core\System\AdminMenu\Command\SyncAdminMenuCommand;
use Shopwell\Core\System\AdminMenu\CoreAdminMenuProvider;
use Shopwell\Core\System\AdminMenu\MenuDefinitionCollector;
use Shopwell\Core\System\AdminMenu\Service\AdminMenuConfigService;
use Shopwell\Core\System\AdminMenu\Service\CoreMenuRowsBuilder;
use Shopwell\Core\System\AdminMenu\Service\CoreMenuSynchronizer;
use Shopwell\Core\System\AdminMenu\Service\PluginMenuSynchronizer;
use Shopwell\Core\System\AdminMenu\Service\VisibleMenuTreeBuilder;
use Shopwell\Core\System\AdminMenu\Subscriber\AdminMenuCacheSubscriber;
use Shopwell\Core\System\AdminMenu\Subscriber\PluginMenuLifecycleSubscriber;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // ---- the entities ----
    $services->set(AdminMenuItemDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AdminMenuItemRoleDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AdminMenuItemTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    // ---- the declaration providers ----
    // the navigation declarations of the core; a plugin adds its own provider, tagged with
    // `shopwell.admin_menu_definition_provider`, in its own service container
    $services->set(CoreAdminMenuProvider::class)
        ->tag('shopwell.admin_menu_definition_provider');

    $services->set(MenuDefinitionCollector::class)
        ->args([tagged_iterator('shopwell.admin_menu_definition_provider')]);

    $services->set(AdminMenuRouteRegistry::class)
        ->args([service(MenuDefinitionCollector::class)]);

    // ---- the services ----
    $services->set(VisibleMenuTreeBuilder::class)
        ->args([
            service('admin_menu_item.repository'),
            service(AdminMenuRouteRegistry::class),
            service(AclRoleLoader::class),
        ]);

    $services->set(AdminMenuConfigService::class)
        ->args([
            service('admin_menu_item.repository'),
            service('acl_role.repository'),
        ]);

    $services->set(PluginMenuSynchronizer::class)
        ->args([
            service('admin_menu_item.repository'),
            service(AdminMenuRouteRegistry::class),
        ]);

    $services->set(CoreMenuRowsBuilder::class);

    // the rows are seeded by the installation migration; this service repairs them for an
    // upgrade, a restored backup or a table that was cleaned up
    $services->set(CoreMenuSynchronizer::class)
        ->args([
            service('admin_menu_item.repository'),
            service(AdminMenuRouteRegistry::class),
            service(CoreMenuRowsBuilder::class),
        ]);

    $services->set(SyncAdminMenuCommand::class)
        ->args([service(CoreMenuSynchronizer::class)])
        ->tag('console.command');

    $services->set(AdminMenuValidator::class)
        ->args([
            service(Connection::class),
            service('admin_menu_item.repository'),
            service(AdminMenuRouteRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AdminMenuCacheSubscriber::class)
        ->args([service(CacheInvalidator::class)])
        ->tag('kernel.event_subscriber');

    $services->set(PluginMenuLifecycleSubscriber::class)
        ->args([service(PluginMenuSynchronizer::class)])
        ->tag('kernel.event_subscriber');

    // ---- the api ----
    $services->set(AdminMenuController::class)
        ->public()
        ->args([
            service('admin_menu_item.repository'),
            service(AdminMenuConfigService::class),
            service(AdminMenuRouteRegistry::class),
            service(VisibleMenuTreeBuilder::class),
        ])
        ->call('setContainer', [service('service_container')]);
};
