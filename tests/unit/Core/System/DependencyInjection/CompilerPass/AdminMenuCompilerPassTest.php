<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\UtilException;
use Shopwell\Core\System\AdminMenu\AdminMenuRouteRegistry;
use Shopwell\Core\System\AdminMenu\AdminMenuXmlReader;
use Symfony\Component\Filesystem\Filesystem;
use Shopwell\Core\System\AdminMenu\CoreAdminMenuProvider;
use Shopwell\Core\System\AdminMenu\MenuDefinitionCollector;
use Shopwell\Core\System\AdminMenu\XmlAdminMenuProvider;
use Shopwell\Core\System\DependencyInjection\CompilerPass\AdminMenuCompilerPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(AdminMenuCompilerPass::class)]
class AdminMenuCompilerPassTest extends TestCase
{
    public function testDiscoversPluginXmlThroughTheRealServiceConfiguration(): void
    {
        $container = $this->createContainer();
        $container->setParameter('kernel.active_plugins', [
            'Example' => ['name' => 'Example', 'path' => __DIR__ . '/../../AdminMenu/_fixtures/Example'],
            'WithoutMenu' => ['name' => 'WithoutMenu', 'path' => __DIR__ . '/../../AdminMenu/_fixtures/WithoutMenu'],
        ]);
        $container->getDefinition(AdminMenuRouteRegistry::class)->setPublic(true);
        $container->getDefinition(MenuDefinitionCollector::class)->setPublic(true);

        (new AdminMenuCompilerPass())->process($container);
        static::assertCount(3, $container->findTaggedServiceIds('shopwell.admin_menu_definition_provider'));
        foreach (array_keys($container->getDefinitions()) as $id) {
            if (!in_array($id, ['service_container', Filesystem::class, AdminMenuXmlReader::class, 'shopwell.admin_menu.xml.WithoutMenu', CoreAdminMenuProvider::class, MenuDefinitionCollector::class, AdminMenuRouteRegistry::class, 'shopwell.admin_menu.xml.Example'], true)) {
                $container->removeDefinition($id);
            }
        }
        $container->compile();

        $registry = $container->get(AdminMenuRouteRegistry::class);
        static::assertInstanceOf(AdminMenuRouteRegistry::class, $registry);
        static::assertSame('sw-system', $registry->get('sw-organization')?->parent);
        static::assertSame('sw-system', $registry->get('example-page')?->parent);
        static::assertSame('example-page', $registry->getPluginDefinitions('Example')[0]->internalKey);
        static::assertSame([], $registry->getPluginDefinitions('WithoutMenu'));
        static::assertNotContains('example-page', array_column($registry->getCoreDefinitions(), 'internalKey'));
        static::assertCount(2, array_filter($container->getResources(), static fn ($resource): bool => $resource instanceof FileExistenceResource));
    }

    public function testInvalidPluginXmlFailsContainerCompilationWithTheFilename(): void
    {
        $container = $this->createContainer();
        $container->setParameter('kernel.active_plugins', [
            'Invalid' => ['name' => 'Invalid', 'path' => __DIR__ . '/../../AdminMenu/_fixtures/Invalid'],
        ]);

        try {
            (new AdminMenuCompilerPass())->process($container);
            static::fail('Invalid plugin XML must fail container compilation.');
        } catch (UtilException $exception) {
            static::assertStringContainsString('admin-menu.xml', $exception->getMessage());
        }
    }

    public function testDoesNothingWhenAdminMenuServicesAreNotRegistered(): void
    {
        $container = new ContainerBuilder();
        (new AdminMenuCompilerPass())->process($container);

        static::assertFalse($container->hasDefinition(CoreAdminMenuProvider::class));
        static::assertFalse($container->hasDefinition(XmlAdminMenuProvider::class));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.active_plugins', []);
        $container->register(Filesystem::class);
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../../../../../src/Core/System/DependencyInjection'));
        $loader->load('admin_menu.php');

        return $container;
    }
}
