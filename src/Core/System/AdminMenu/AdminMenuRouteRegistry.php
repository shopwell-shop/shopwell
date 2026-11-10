<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu;

use Shopwell\Core\Framework\Log\Package;

/**
 * The whitelist of navigation entries the administration is allowed to point at.
 *
 * The database only manages the navigation layer - the routes and the components behind
 * them are registered in code. A menu item that points at a route nobody registered
 * would render as a dead link, so every write of an `internal_key` is validated against
 * this registry and rejected when the key is not declared by any provider.
 *
 * The core declarations of the registry are also the seed source of the navigation
 * table: the plugin lifecycle upserts them, and the visible tree renders entries the
 * database does not know yet from them, so a core upgrade cannot lose new menu items.
 */
#[Package('fundamentals@framework')]
class AdminMenuRouteRegistry
{
    /**
     * @var array<string, AdminMenuDefinitionStruct>|null indexed by internal key, built on first access
     */
    private ?array $index = null;

    /**
     * @internal
     */
    public function __construct(private readonly MenuDefinitionCollector $collector)
    {
    }

    public function has(string $internalKey): bool
    {
        return isset($this->getIndex()[$internalKey]);
    }

    public function get(string $internalKey): ?AdminMenuDefinitionStruct
    {
        return $this->getIndex()[$internalKey] ?? null;
    }

    /**
     * @return list<AdminMenuDefinitionStruct>
     */
    public function getDefinitions(): array
    {
        return array_values($this->getIndex());
    }

    /**
     * @return list<AdminMenuDefinitionStruct>
     */
    public function getCoreDefinitions(): array
    {
        return $this->collector->collectCore();
    }

    /**
     * @return list<AdminMenuDefinitionStruct>
     */
    public function getPluginDefinitions(string $technicalName): array
    {
        return $this->collector->collectForPlugin($technicalName);
    }

    /**
     * @return array<string, AdminMenuDefinitionStruct>
     */
    private function getIndex(): array
    {
        // The definitions are code, so the index is built once per container lifetime
        // and never invalidated.
        $this->index ??= array_column($this->collector->collectAll(), null, 'internalKey');

        return $this->index;
    }
}
