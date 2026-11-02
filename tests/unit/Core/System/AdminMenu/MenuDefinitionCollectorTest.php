<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionProviderInterface;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionStruct;
use Shopwell\Core\System\AdminMenu\MenuDefinitionCollector;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(MenuDefinitionCollector::class)]
class MenuDefinitionCollectorTest extends TestCase
{
    public function testCollectCoreReturnsOnlyCoreDefinitions(): void
    {
        $collector = $this->createCollector();

        static::assertSame(
            ['sw-dashboard', 'sw-catalogue'],
            $this->internalKeys($collector->collectCore())
        );
    }

    public function testCollectForPluginReturnsOnlyTheDefinitionsOfThatPlugin(): void
    {
        $collector = $this->createCollector();

        static::assertSame(
            ['sw-plugin-entry', 'sw-plugin-settings'],
            $this->internalKeys($collector->collectForPlugin('SwagExample'))
        );
    }

    public function testCollectForAnUnknownPluginReturnsNothing(): void
    {
        $collector = $this->createCollector();

        static::assertSame([], $collector->collectForPlugin('SwagNotInstalled'));
    }

    public function testCollectAllKeepsTheOrderOfTheProviders(): void
    {
        $collector = $this->createCollector();

        static::assertSame(
            ['sw-dashboard', 'sw-catalogue', 'sw-plugin-entry', 'sw-plugin-settings'],
            $this->internalKeys($collector->collectAll())
        );
    }

    private function createCollector(): MenuDefinitionCollector
    {
        return new MenuDefinitionCollector([
            $this->createProvider(null, [
                new AdminMenuDefinitionStruct('sw-dashboard'),
                new AdminMenuDefinitionStruct('sw-catalogue'),
            ]),
            $this->createProvider('SwagExample', [
                new AdminMenuDefinitionStruct('sw-plugin-entry'),
                new AdminMenuDefinitionStruct('sw-plugin-settings'),
            ]),
        ]);
    }

    /**
     * @param list<AdminMenuDefinitionStruct> $definitions
     */
    private function createProvider(?string $pluginTechnicalName, array $definitions): AdminMenuDefinitionProviderInterface
    {
        return new class($pluginTechnicalName, $definitions) implements AdminMenuDefinitionProviderInterface {
            /**
             * @param list<AdminMenuDefinitionStruct> $definitions
             */
            public function __construct(
                private readonly ?string $pluginTechnicalName,
                private readonly array $definitions,
            ) {
            }

            public function getPluginTechnicalName(): ?string
            {
                return $this->pluginTechnicalName;
            }

            public function getDefinitions(): array
            {
                return $this->definitions;
            }
        };
    }

    /**
     * @param list<AdminMenuDefinitionStruct> $definitions
     *
     * @return list<string>
     */
    private function internalKeys(array $definitions): array
    {
        return array_map(static fn (AdminMenuDefinitionStruct $definition): string => $definition->internalKey, $definitions);
    }
}
