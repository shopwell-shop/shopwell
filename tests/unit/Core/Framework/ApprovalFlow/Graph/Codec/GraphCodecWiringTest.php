<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\Codec;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphCodec;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeSourceDirectory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\YamlNodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Mutation\GraphMutator;
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
#[CoversClass(GraphCodec::class)]
class GraphCodecWiringTest extends TestCase
{
    public function testBusinessNodeParticipatesInCodecAndMutationThroughActualServiceAssembly(): void
    {
        $container = new ContainerBuilder();
        $container->register('filesystem', Filesystem::class);
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 7) . '/src/Core/Framework/DependencyInjection'));
        $loader->load('approval-flow.php');
        $container->register('finance.approval.node_loader', YamlNodeTypeLoader::class)
            ->setArguments([
                new Reference('filesystem'),
                [new Definition(NodeTypeSourceDirectory::class, ['finance', __DIR__ . '/../Type/Registry/_fixtures'])],
            ])
            ->addTag('approval_flow.node_type_loader');
        $container->getDefinition(GraphCodec::class)->setPublic(true);
        $container->getDefinition(GraphMutator::class)->setPublic(true);
        $container->compile();
        $codec = $container->get(GraphCodec::class);
        $mutator = $container->get(GraphMutator::class);
        static::assertInstanceOf(GraphCodec::class, $codec);
        static::assertInstanceOf(GraphMutator::class, $mutator);
        $json = (new Filesystem())->readFile(__DIR__ . '/../_fixtures/finance-transfer.json');

        $graph = $codec->decodeDraft($json);
        $encoded = $codec->encodeDraft($graph);
        foreach (['"limit":0', '"enabled":false', '"note":null', '"options":{}', '"groups":[]'] as $fragment) {
            static::assertStringContainsString($fragment, $encoded);
        }
        $removed = $mutator->removeNode($graph, 'review');
        static::assertStringContainsString('"type":"finance:review"', $removed->removedNodes[0]);
        static::assertStringContainsString('"note":null', $removed->removedNodes[0]);
        static::assertSame($encoded, $graph->json);
        static::assertTrue($removed->diagnostics->isWritable());
    }
}
