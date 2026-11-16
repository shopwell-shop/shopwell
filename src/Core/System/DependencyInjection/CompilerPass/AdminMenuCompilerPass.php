<?php declare(strict_types=1);

namespace Shopwell\Core\System\DependencyInjection\CompilerPass;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\AdminMenuXmlReader;
use Shopwell\Core\System\AdminMenu\CoreAdminMenuProvider;
use Shopwell\Core\System\AdminMenu\XmlAdminMenuProvider;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
final class AdminMenuCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(CoreAdminMenuProvider::class)) {
            return;
        }

        $reader = new AdminMenuXmlReader();
        $filesystem = new Filesystem();
        $container->addResource(new FileResource(AdminMenuXmlReader::SCHEMA_FILE));
        $container->addResource(new FileResource(CoreAdminMenuProvider::MENU_FILE));
        $container->getDefinition(CoreAdminMenuProvider::class)
            ->setArgument(0, $reader->read(CoreAdminMenuProvider::MENU_FILE));

        /** @var array<string, array{name: string, path: string, class: string}> $plugins */
        $plugins = $container->getParameter('kernel.active_plugins');
        foreach ($plugins as $plugin) {
            $file = $plugin['path'] . '/Resources/config/admin-menu.xml';
            // Track absent files as well: adding a menu file must invalidate the dev container.
            $container->addResource(new FileExistenceResource($file));
            $definitions = [];
            if ($filesystem->exists($file)) {
                $container->addResource(new FileResource($file));
                $definitions = $reader->read($file);
            }
            $provider = new Definition(XmlAdminMenuProvider::class, [
                $definitions,
                $plugin['name'],
                $file,
                new Reference(AdminMenuXmlReader::class),
                new Reference(Filesystem::class),
            ]);
            $provider->addTag('shopwell.admin_menu_definition_provider');
            $container->setDefinition('shopwell.admin_menu.xml.' . $plugin['name'], $provider);
        }
    }
}
