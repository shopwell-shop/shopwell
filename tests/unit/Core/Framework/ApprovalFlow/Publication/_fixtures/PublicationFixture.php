<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures;

use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\ApprovalExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\EndExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\StartExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphSchemaValidator;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\CoreNodeCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeCompilerRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Publication\ApprovalReleaseCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Publication\Artifact\ArtifactManifestLoader;
use Shopwell\Core\Framework\ApprovalFlow\Publication\NodePlanCompiler;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Synthetic deployment attestation for composition tests, not a production build artifact.
 *
 * @internal
 */
final class PublicationFixture
{
    public static function container(string $manifestPath): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('filesystem', Filesystem::class);
        (new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 7) . '/src/Core/Framework/DependencyInjection')))->load('approval-flow.php');
        $container->getDefinition(ArtifactManifestLoader::class)->setArgument(4, $manifestPath);
        $container->getDefinition(ApprovalReleaseCompiler::class)->setPublic(true);

        return $container;
    }

    public static function releaseCompiler(
        NodePlanCompiler $nodePlanCompiler,
        NodeCompilerRegistry $nodeCompilers,
        NodeExecutorRegistry $nodeExecutors,
        Filesystem $filesystem,
        string $manifestPath,
    ): ApprovalReleaseCompiler {
        $schema = $filesystem->readFile(\dirname(__DIR__, 7) . '/src/Core/Framework/ApprovalFlow/Resources/schema/publication/1.0.0/artifact-manifest.schema.json');

        return new ApprovalReleaseCompiler(
            $nodePlanCompiler,
            $nodeCompilers,
            $nodeExecutors,
            new GraphJsonDecoder(),
            new ArtifactManifestLoader($filesystem, new GraphJsonDecoder(), new GraphSchemaValidator(), $schema, $manifestPath),
        );
    }

    /**
     * @param list<class-string> $classes
     */
    public static function writeManifest(Filesystem $filesystem, string $directory, array $classes = []): string
    {
        $bytes = 'Synthetic retained bytes for test deployment attestation.';
        $filesystem->dumpFile($directory . '/core.artifact', $bytes);
        $services = [];
        foreach (array_merge([ApprovalReleaseCompiler::class, ArtifactManifestLoader::class, NodePlanCompiler::class, CoreNodeCompiler::class, StartExecutor::class, ApprovalExecutor::class, EndExecutor::class], $classes) as $class) {
            $services[] = ['class' => $class, 'artifact' => 'test:application@1.0.0'];
        }
        $path = $directory . '/manifest.json';
        $filesystem->dumpFile($path, json_encode([
            'formatVersion' => '1.0.0',
            'artifacts' => [['id' => 'test:application', 'version' => '1.0.0', 'file' => 'core.artifact', 'sha256' => hash('sha256', $bytes), 'requires' => []]],
            'services' => $services,
        ], \JSON_THROW_ON_ERROR));

        return $path;
    }
}
