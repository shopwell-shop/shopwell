<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu;

use Shopwell\Core\Framework\Log\Package;

/**
 * Collects declarations by owner and validates the combined graph before any owner is synced.
 * Plugins may attach entries to core menus or their own entries, but cannot depend on another
 * plugin's menu lifecycle. Duplicate identities are always an error, never an override.
 */
#[Package('fundamentals@framework')]
class MenuDefinitionCollector
{
    /**
     * @var array<string, list<AdminMenuDefinitionStruct>>|null
     */
    private ?array $definitionsByOwner = null;

    /**
     * @internal
     *
     * @param iterable<AdminMenuDefinitionProviderInterface> $providers
     */
    public function __construct(private readonly iterable $providers)
    {
    }

    /**
     * Re-read a plugin's XML at its lifecycle boundary, then validate a fresh graph.
     */
    public function refreshForPlugin(string $technicalName): void
    {
        foreach ($this->providers as $provider) {
            if ($provider instanceof XmlAdminMenuProvider && $provider->getPluginTechnicalName() === $technicalName) {
                $provider->refresh();
            }
        }
        $this->definitionsByOwner = null;
    }

    /**
     * @return list<AdminMenuDefinitionStruct>
     */
    public function collectCore(): array
    {
        return $this->getDefinitionsByOwner()[''] ?? [];
    }

    /**
     * @return list<AdminMenuDefinitionStruct>
     */
    public function collectForPlugin(string $technicalName): array
    {
        return $this->getDefinitionsByOwner()[$technicalName] ?? [];
    }

    /**
     * @return list<AdminMenuDefinitionStruct>
     */
    public function collectAll(): array
    {
        return array_merge([], ...array_values($this->getDefinitionsByOwner()));
    }

    /**
     * @return array<string, list<AdminMenuDefinitionStruct>>
     */
    private function getDefinitionsByOwner(): array
    {
        if ($this->definitionsByOwner !== null) {
            return $this->definitionsByOwner;
        }

        $byOwner = [];
        foreach ($this->providers as $provider) {
            $owner = $provider->getPluginTechnicalName() ?? '';
            $byOwner[$owner] = [...($byOwner[$owner] ?? []), ...$provider->getDefinitions()];
        }

        $index = [];
        $owners = [];
        foreach ($byOwner as $owner => $definitions) {
            $byOwner[$owner] = MenuGroupNormalizer::normalize($definitions, $owner === '' ? null : $owner);
            foreach ($byOwner[$owner] as $definition) {
                if (isset($index[$definition->internalKey])) {
                    throw AdminMenuException::invalidDefinition($definition->internalKey, 'Duplicate identity in menu providers.');
                }

                $index[$definition->internalKey] = $definition;
                $owners[$definition->internalKey] = $owner;
            }
        }

        $this->validateParents($index, $owners);
        $this->validateCycles($index);
        $this->definitionsByOwner = $byOwner;

        return $byOwner;
    }

    /**
     * @param array<string, AdminMenuDefinitionStruct> $index
     * @param array<string, string> $owners
     */
    private function validateParents(array $index, array $owners): void
    {
        foreach ($index as $key => $definition) {
            $parent = $definition->parent;
            if ($parent === null) {
                continue;
            }
            if (!isset($index[$parent])) {
                throw AdminMenuException::invalidDefinition($key, \sprintf('Parent "%s" is not declared.', $parent));
            }
            if ($owners[$parent] !== '' && $owners[$parent] !== $owners[$key]) {
                throw AdminMenuException::invalidDefinition($key, \sprintf('Parent "%s" belongs to plugin "%s".', $parent, $owners[$parent]));
            }
        }
    }

    /**
     * @param array<string, AdminMenuDefinitionStruct> $index
     */
    private function validateCycles(array $index): void
    {
        $complete = [];
        foreach ($index as $key => $definition) {
            $path = [];
            $cursor = $key;
            while ($cursor !== null && !isset($complete[$cursor])) {
                if (isset($path[$cursor])) {
                    throw AdminMenuException::invalidDefinition($key, \sprintf('Parent cycle includes "%s".', $cursor));
                }

                $path[$cursor] = true;
                $cursor = $index[$cursor]->parent;
            }
            $complete += $path;
        }
    }
}
