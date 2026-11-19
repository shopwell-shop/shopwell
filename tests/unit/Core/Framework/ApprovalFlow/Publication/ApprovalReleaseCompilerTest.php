<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\EndExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeSourceDirectory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\YamlNodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Publication\ApprovalReleaseCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Publication\Artifact\ArtifactManifestLoader;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\FinanceNodeCompiler;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\FinanceNodeExecutor;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalReleaseCompiler::class)]
class ApprovalReleaseCompilerTest extends TestCase
{
    use EnvTestBehaviour;

    private Filesystem $filesystem;

    private string $directory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/approval-publication-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testPublicationAssemblyRequiresDeploymentConfiguration(): void
    {
        $this->setEnvVars(['APPROVAL_FLOW_ARTIFACT_MANIFEST' => null]);
        $container = new ContainerBuilder();
        $container->register('filesystem', Filesystem::class);
        (new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 6) . '/src/Core/Framework/DependencyInjection')))->load('approval-flow.php');
        $path = $container->getDefinition(ArtifactManifestLoader::class)->getArgument(4);
        static::assertIsString($path);
        $this->expectException(EnvNotFoundException::class);

        $container->resolveEnvPlaceholders($path, true);
    }

    public function testRealAssemblyFreezesTheExactUsedServicesAndArtifactBytes(): void
    {
        $compiler = $this->compiler();

        $release = $compiler->compile(new DraftGraph(GraphFixture::json()));

        $dependencies = json_decode($release->dependenciesJson, associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertContains(EndExecutor::class, array_column($dependencies['services'], 'class'));
        static::assertSame(hash('sha256', $this->filesystem->readFile($this->directory . '/core.artifact')), $dependencies['artifacts'][0]['sha256']);
        static::assertSame($release->hash, $compiler->compile(new DraftGraph(str_replace('    ', '', GraphFixture::json())))->hash);
        static::assertSame($release->nodePlan->graphPlan->snapshot->graphJson, $release->graphJson);
    }

    public function testBusinessServicesAreFrozenThroughTheirOwnTaggedAssembly(): void
    {
        $path = PublicationFixture::writeManifest($this->filesystem, $this->directory, [FinanceNodeCompiler::class, FinanceNodeExecutor::class]);
        $container = PublicationFixture::container($path);
        $container->register('finance.node_loader', YamlNodeTypeLoader::class)
            ->setArguments([
                new Reference('filesystem'),
                [new Definition(NodeTypeSourceDirectory::class, ['finance', __DIR__ . '/../Graph/Type/Registry/_fixtures'])],
            ])
            ->addTag('approval_flow.node_type_loader');
        $container->register(FinanceNodeCompiler::class)->addTag('approval_flow.node_compiler');
        $container->register(FinanceNodeExecutor::class)->addTag('approval_flow.node_executor');
        $container->compile();
        $compiler = $container->get(ApprovalReleaseCompiler::class);
        static::assertInstanceOf(ApprovalReleaseCompiler::class, $compiler);
        $graph = $this->filesystem->readFile(__DIR__ . '/../Graph/_fixtures/finance-transfer.json');

        $release = $compiler->compile(new DraftGraph($graph));

        $dependencies = json_decode($release->dependenciesJson, associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertContains(FinanceNodeCompiler::class, array_column($dependencies['services'], 'class'));
        static::assertContains(FinanceNodeExecutor::class, array_column($dependencies['services'], 'class'));
    }

    public function testMissingExactExecutorArtifactPreventsPublicationPreparation(): void
    {
        $compiler = $this->compiler();
        $path = $this->directory . '/manifest.json';
        $manifest = json_decode($this->filesystem->readFile($path), associative: true, flags: \JSON_THROW_ON_ERROR);
        $manifest['services'] = array_values(array_filter($manifest['services'], static fn (array $service): bool => $service['class'] !== EndExecutor::class));
        $this->filesystem->dumpFile($path, json_encode($manifest, \JSON_THROW_ON_ERROR));
        $this->expectExceptionObject(ApprovalFlowException::artifactServiceNotFound(EndExecutor::class));

        $compiler->compile(new DraftGraph(GraphFixture::json()));
    }

    public function testChangedCodeArtifactChangesTheFullPublicationHash(): void
    {
        $compiler = $this->compiler();
        $first = $compiler->compile(new DraftGraph(GraphFixture::json()));
        $path = $this->directory . '/manifest.json';
        $manifest = json_decode($this->filesystem->readFile($path), associative: true, flags: \JSON_THROW_ON_ERROR);
        $this->filesystem->dumpFile($this->directory . '/core.artifact', 'A different synthetic deployment.');
        $manifest['artifacts'][0]['sha256'] = hash('sha256', 'A different synthetic deployment.');
        $this->filesystem->dumpFile($path, json_encode($manifest, \JSON_THROW_ON_ERROR));

        $second = $compiler->compile(new DraftGraph(GraphFixture::json()));

        static::assertSame($first->nodePlan->hash, $second->nodePlan->hash);
        static::assertNotSame($first->hash, $second->hash);
    }

    public function testChangedGraphCannotKeepAnEarlierPublicationHash(): void
    {
        $compiler = $this->compiler();
        $first = $compiler->compile(new DraftGraph(GraphFixture::json()));

        $second = $compiler->compile(new DraftGraph(str_replace('"outcome": "approved"', '"outcome": "rejected"', GraphFixture::json())));

        static::assertSame($first->dependenciesJson, $second->dependenciesJson);
        static::assertNotSame($first->hash, $second->hash);
    }

    private function compiler(): ApprovalReleaseCompiler
    {
        $container = PublicationFixture::container(PublicationFixture::writeManifest($this->filesystem, $this->directory));
        $container->compile();
        $compiler = $container->get(ApprovalReleaseCompiler::class);
        static::assertInstanceOf(ApprovalReleaseCompiler::class, $compiler);

        return $compiler;
    }
}
