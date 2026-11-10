<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionProviderInterface;
use Shopwell\Core\System\AdminMenu\AdminMenuDefinitionStruct;
use Shopwell\Core\System\AdminMenu\AdminMenuItemDefinition;
use Shopwell\Core\System\AdminMenu\AdminMenuRouteRegistry;
use Shopwell\Core\System\AdminMenu\MenuDefinitionCollector;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(AdminMenuRouteRegistry::class)]
class AdminMenuRouteRegistryTest extends TestCase
{
    public function testWhitelistsTheKeysOfCoreAndPluginDeclarations(): void
    {
        $registry = new AdminMenuRouteRegistry(new MenuDefinitionCollector([
            new CoreMenuProvider(['sw-product']),
            new PluginMenuProvider('SwagExample', ['swag-example-route']),
        ]));

        static::assertTrue($registry->has('sw-product'));
        static::assertTrue($registry->has('swag-example-route'));
        static::assertNull($registry->get('not-declared'));
    }

    public function testListsCoreAndPluginDefinitionsForNavigationConsumers(): void
    {
        $registry = new AdminMenuRouteRegistry(new MenuDefinitionCollector([
            new CoreMenuProvider(['sw-product']),
            new PluginMenuProvider('SwagExample', ['swag-example-route']),
        ]));

        static::assertSame(
            ['sw-product', 'swag-example-route'],
            array_map(static fn (AdminMenuDefinitionStruct $definition): string => $definition->internalKey, $registry->getDefinitions())
        );
    }

    public function testCoreDefinitionsDoNotContainPluginDeclarations(): void
    {
        $registry = new AdminMenuRouteRegistry(new MenuDefinitionCollector([
            new CoreMenuProvider(['sw-product']),
            new PluginMenuProvider('SwagExample', ['swag-example-route']),
        ]));

        static::assertSame(
            ['sw-product'],
            array_map(static fn (AdminMenuDefinitionStruct $definition): string => $definition->internalKey, $registry->getCoreDefinitions())
        );
    }

    public function testPluginDefinitionsAreFoundByTheirTechnicalName(): void
    {
        $registry = new AdminMenuRouteRegistry(new MenuDefinitionCollector([
            new CoreMenuProvider(['sw-product']),
            new PluginMenuProvider('SwagExample', ['swag-example-route']),
        ]));

        static::assertSame(
            ['swag-example-route'],
            array_map(static fn (AdminMenuDefinitionStruct $definition): string => $definition->internalKey, $registry->getPluginDefinitions('SwagExample'))
        );
        static::assertSame([], $registry->getPluginDefinitions('SwagOther'));
    }
}

/**
 * @internal
 */
class CoreMenuProvider implements AdminMenuDefinitionProviderInterface
{
    /**
     * @param list<string> $internalKeys
     */
    public function __construct(private readonly array $internalKeys)
    {
    }

    public function getPluginTechnicalName(): ?string
    {
        return null;
    }

    public function getDefinitions(): array
    {
        return array_map(
            static fn (string $internalKey): AdminMenuDefinitionStruct => new AdminMenuDefinitionStruct(
                internalKey: $internalKey,
                type: AdminMenuItemDefinition::TYPE_FOLDER
            ),
            $this->internalKeys
        );
    }
}

/**
 * @internal
 */
class PluginMenuProvider implements AdminMenuDefinitionProviderInterface
{
    /**
     * @param list<string> $internalKeys
     */
    public function __construct(
        private readonly string $technicalName,
        private readonly array $internalKeys,
    ) {
    }

    public function getPluginTechnicalName(): ?string
    {
        return $this->technicalName;
    }

    public function getDefinitions(): array
    {
        return array_map(
            static fn (string $internalKey): AdminMenuDefinitionStruct => new AdminMenuDefinitionStruct($internalKey),
            $this->internalKeys
        );
    }
}
