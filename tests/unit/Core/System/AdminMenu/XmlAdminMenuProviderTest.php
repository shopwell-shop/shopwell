<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\AdminMenuRouteRegistry;
use Shopwell\Core\System\AdminMenu\AdminMenuXmlReader;
use Shopwell\Core\System\AdminMenu\MenuDefinitionCollector;
use Shopwell\Core\System\AdminMenu\XmlAdminMenuProvider;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(XmlAdminMenuProvider::class)]
class XmlAdminMenuProviderTest extends TestCase
{
    public function testPreservesPluginOwnershipAndCompiledMetadata(): void
    {
        $rows = (new AdminMenuXmlReader())->read(__DIR__ . '/_fixtures/valid-menu.xml');
        $provider = new XmlAdminMenuProvider($rows, 'Example');

        static::assertSame('Example', $provider->getPluginTechnicalName());
        $definitions = $provider->getDefinitions();
        static::assertSame('example-page', $definitions[1]->internalKey);
        static::assertSame('example.viewer', $definitions[1]->privilege);
        static::assertSame(20, $definitions[1]->position);
        static::assertSame('sw-system', $definitions[1]->parent);
        static::assertSame('https://example.com/help', $definitions[2]->linkTarget);
    }

    public function testUpdateRefreshesXmlDeclarationsAfterTheContainerWasBuilt(): void
    {
        $filesystem = new Filesystem();
        $directory = sys_get_temp_dir() . '/menu-update-' . bin2hex(random_bytes(6));
        $file = $directory . '/admin-menu.xml';
        try {
            $filesystem->dumpFile($file, '<admin-menu><menu id="old-page"><title lang="en-GB">Old</title></menu></admin-menu>');
            $reader = new AdminMenuXmlReader();
            $provider = new XmlAdminMenuProvider($reader->read($file), 'Example', $file, $reader, $filesystem);
            $registry = new AdminMenuRouteRegistry(new MenuDefinitionCollector([$provider]));
            static::assertTrue($registry->has('old-page'));
            $filesystem->dumpFile($file, '<admin-menu><menu id="new-page"><title lang="en-GB">New</title></menu></admin-menu>');

            $registry->refreshPluginDefinitions('Example');

            static::assertFalse($registry->has('old-page'));
            static::assertTrue($registry->has('new-page'));
            static::assertSame('new-page', $registry->getPluginDefinitions('Example')[0]->internalKey);
            $filesystem->remove($file);
            $registry->refreshPluginDefinitions('Example');
            static::assertSame([], $registry->getPluginDefinitions('Example'));
        } finally {
            $filesystem->remove($directory);
        }
    }

    public function testCoreDefaultsHaveNoPluginOwner(): void
    {
        $provider = new XmlAdminMenuProvider([]);

        static::assertNull($provider->getPluginTechnicalName());
        static::assertSame([], $provider->getDefinitions());
    }
}
