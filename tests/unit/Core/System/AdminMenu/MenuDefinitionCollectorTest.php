<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionProviderInterface;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionStruct;
use Shopwell\Core\System\AdminMenu\AdminMenuException;
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

    public function testRejectsDuplicateKeysAcrossOwners(): void
    {
        $collector = new MenuDefinitionCollector([
            $this->createProvider(null, [new AdminMenuDefinitionStruct('sw-system')]),
            $this->createProvider('Example', [new AdminMenuDefinitionStruct('sw-system')]),
        ]);

        $this->expectExceptionObject(AdminMenuException::invalidDefinition('sw-system', 'Duplicate identity in menu providers.'));
        $collector->collectForPlugin('Example');
    }

    public function testRejectsMissingParents(): void
    {
        $collector = new MenuDefinitionCollector([
            $this->createProvider(null, [new AdminMenuDefinitionStruct('page', parent: 'missing')]),
        ]);

        $this->expectExceptionObject(AdminMenuException::invalidDefinition('page', 'Parent "missing" is not declared.'));
        $collector->collectAll();
    }

    public function testRejectsParentCycles(): void
    {
        $collector = new MenuDefinitionCollector([
            $this->createProvider(null, [
                new AdminMenuDefinitionStruct('a', parent: 'b'),
                new AdminMenuDefinitionStruct('b', parent: 'a'),
            ]),
        ]);

        $this->expectExceptionObject(AdminMenuException::invalidDefinition('a', 'Parent cycle includes "a".'));
        $collector->collectCore();
    }

    public function testRejectsParentsOwnedByAnotherPlugin(): void
    {
        $collector = new MenuDefinitionCollector([
            $this->createProvider('First', [new AdminMenuDefinitionStruct('first')]),
            $this->createProvider('Second', [new AdminMenuDefinitionStruct('second', parent: 'first')]),
        ]);

        $this->expectExceptionObject(AdminMenuException::invalidDefinition('second', 'Parent "first" belongs to plugin "First".'));
        $collector->collectAll();
    }

    public function testPluginMayUseItsOwnParentAndCoreParents(): void
    {
        $collector = new MenuDefinitionCollector([
            $this->createProvider('Example', [
                new AdminMenuDefinitionStruct('child', parent: 'parent'),
                new AdminMenuDefinitionStruct('parent', parent: 'sw-system'),
            ]),
            $this->createProvider(null, [new AdminMenuDefinitionStruct('sw-system')]),
        ]);

        static::assertSame(['child', 'parent'], $this->internalKeys($collector->collectForPlugin('Example')));
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
