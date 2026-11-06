<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu;

use Shopwell\Core\Framework\Log\Package;

/**
 * Declares administration navigation entries from code, so the plugin lifecycle can sync
 * them into the `admin_menu_item` table without the plugin touching that table itself.
 *
 * A plugin registers one provider in its own service container, tagged with
 * `shopwell.admin_menu_definition_provider`. The definitions of a provider always belong
 * to the plugin that declares it, so no single definition carries ownership data - the
 * owning plugin is resolved from {@link getPluginTechnicalName()} once per provider.
 *
 * Core navigation is declared the same way by {@link CoreAdminMenuProvider}, which keeps
 * the route whitelist in {@link AdminMenuRouteRegistry} the single source of truth for
 * every navigation entry the administration is allowed to point at.
 */
#[Package('fundamentals@framework')]
interface AdminMenuDefinitionProviderInterface
{
    /**
     * Technical name of the plugin that declares the definitions, or null for the core.
     */
    public function getPluginTechnicalName(): ?string;

    /**
     * @return list<AdminMenuDefinitionStruct>
     */
    public function getDefinitions(): array;
}
