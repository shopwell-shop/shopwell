<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\Type\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeSourceDirectory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\YamlNodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NodeTypeRegistry::class)]
class NodeTypeRegistryWiringTest extends TestCase
{
    public function testDiscoversBusinessOwnedDeclarationsThroughTheRealTaggedContainer(): void
    {
        $container = new ContainerBuilder();
        $container->register('filesystem', Filesystem::class);
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 8) . '/src/Core/Framework/DependencyInjection'));
        $loader->load('approval-flow.php');
        $container->register('finance.approval.node_loader', YamlNodeTypeLoader::class)
            ->setArguments([
                new Reference('filesystem'),
                [new Definition(NodeTypeSourceDirectory::class, ['finance', __DIR__ . '/_fixtures'])],
            ])
            ->addTag('approval_flow.node_type_loader');
        $container->getDefinition(NodeTypeRegistry::class)->setPublic(true);
        $container->compile();

        $registry = $container->get(NodeTypeRegistry::class);
        static::assertInstanceOf(NodeTypeRegistry::class, $registry);
        static::assertCount(4, $registry->all());
        static::assertSame('finance:review.yaml', $registry->get('finance:review', '1.0.0')->source);
        static::assertSame('core:start.yaml', $registry->get('core:start', '1.0.0')->source);
    }
}
