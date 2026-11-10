<?php declare(strict_types=1);

namespace Shopwell\Core\System\AdminMenu;

use Shopwell\Core\Framework\Log\Package;

/**
 * Collects the navigation declarations of every registered
 * {@link AdminMenuDefinitionProviderInterface} - the core provider and one provider per
 * plugin that registers the `shopwell.admin_menu_definition_provider` tag.
 *
 * The collector keeps the providers grouped by their owning plugin, so the plugin
 * lifecycle can sync exactly the definitions of the plugin whose lifecycle event is
 * being handled, while the route whitelist only needs the flattened set of internal keys.
 *
 * @see AdminMenuRouteRegistry
 */
#[Package('fundamentals@framework')]
class MenuDefinitionCollector
{
    /**
     * @internal
     *
     * @param iterable<AdminMenuDefinitionProviderInterface> $providers
     */
    public function __construct(private readonly iterable $providers)
    {
    }

    /**
     * @return list<AdminMenuDefinitionStruct>
     */
    public function collectCore(): array
    {
        return $this->collect(fn (AdminMenuDefinitionProviderInterface $provider): bool => $provider->getPluginTechnicalName() === null);
    }

    /**
     * @return list<AdminMenuDefinitionStruct>
     */
    public function collectForPlugin(string $technicalName): array
    {
        return $this->collect(fn (AdminMenuDefinitionProviderInterface $provider): bool => $provider->getPluginTechnicalName() === $technicalName);
    }

    /**
     * @return list<AdminMenuDefinitionStruct>
     */
    public function collectAll(): array
    {
        return $this->collect(static fn (): bool => true);
    }

    /**
     * @param \Closure(AdminMenuDefinitionProviderInterface): bool $filter
     *
     * @return list<AdminMenuDefinitionStruct>
     */
    private function collect(\Closure $filter): array
    {
        $definitions = [];

        foreach ($this->providers as $provider) {
            if (!$filter($provider)) {
                continue;
            }

            $definitions = [...$definitions, ...MenuGroupNormalizer::normalize($provider->getDefinitions(), $provider->getPluginTechnicalName())];
        }

        return $definitions;
    }
}
