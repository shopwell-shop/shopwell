<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\Artifact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\ApprovalExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\EndExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphSchemaValidator;
use Shopwell\Core\Framework\ApprovalFlow\Publication\Artifact\ArtifactManifestLoader;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ArtifactManifestLoader::class)]
class ArtifactManifestLoaderTest extends TestCase
{
    private Filesystem $filesystem;

    private string $directory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/approval-artifacts-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->directory);
        $this->filesystem->dumpFile($this->directory . '/runtime.php', '<?php /* retained runtime fixture */');
        $this->filesystem->dumpFile($this->directory . '/review.php', '<?php /* retained review fixture */');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testFreezesOnlyUsedArtifactsWithTheirTransitiveDependencies(): void
    {
        $manifest = $this->manifest();
        $manifest['artifacts'][] = ['id' => 'unused:code', 'version' => '1.0.0', 'file' => 'unavailable.php', 'sha256' => str_repeat('a', 64), 'requires' => []];
        $this->writeManifest($manifest);

        $json = $this->loader()->load([EndExecutor::class]);

        $frozen = json_decode($json, associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(['core:runtime', 'finance:review'], array_column($frozen['artifacts'], 'id'));
        static::assertSame([EndExecutor::class], array_column($frozen['services'], 'class'));
        static::assertStringNotContainsString($this->directory, $json);
        static::assertSame($json, $this->loader()->load([EndExecutor::class, EndExecutor::class]));
    }

    public function testUnreadableManifestPreservesTheFilesystemCause(): void
    {
        try {
            $this->loader()->load([EndExecutor::class]);
            static::fail('An unreadable deployment manifest must fail.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_ARTIFACT_MANIFEST, $exception->getErrorCode());
            static::assertInstanceOf(IOExceptionInterface::class, $exception->getPrevious());
        }
    }

    public function testDistinctArtifactVersionsCanBeRetainedTogetherAndOrderingDoesNotChangeTheFrozenBytes(): void
    {
        $manifest = $this->manifest();
        $secondVersion = $manifest['artifacts'][0];
        $secondVersion['version'] = '2.0.0';
        $manifest['artifacts'][] = $secondVersion;
        $manifest['artifacts'][1]['requires'][] = 'core:runtime@2.0.0';
        $this->writeManifest($manifest);
        $first = $this->loader()->load([EndExecutor::class]);
        $manifest['artifacts'] = array_reverse($manifest['artifacts']);
        $manifest['artifacts'][1]['requires'] = array_reverse($manifest['artifacts'][1]['requires']);
        $this->writeManifest($manifest);

        $second = $this->loader()->load([EndExecutor::class]);

        static::assertSame($first, $second);
        $frozen = json_decode($second, associative: true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(['1.0.0', '2.0.0', '1.0.0'], array_column($frozen['artifacts'], 'version'));
    }

    public function testMissingRetainedArtifactBytesPreventFreezingTheDependencySet(): void
    {
        $this->writeManifest($this->manifest());
        $this->filesystem->remove($this->directory . '/review.php');
        $this->expectExceptionObject(ApprovalFlowException::invalidArtifactManifest('Artifact "finance:review@1.0.0" has no readable retained bytes.'));

        $this->loader()->load([EndExecutor::class]);
    }

    public function testUnboundServiceCannotUseAnotherServicesArtifact(): void
    {
        $this->writeManifest($this->manifest());
        $this->expectExceptionObject(ApprovalFlowException::artifactServiceNotFound(ApprovalExecutor::class));

        $this->loader()->load([ApprovalExecutor::class]);
    }

    public function testChangedRetainedBytesAreRejectedWithoutReusingAnEarlierLoad(): void
    {
        $this->writeManifest($this->manifest());
        $loader = $this->loader();
        $loader->load([EndExecutor::class]);
        $this->filesystem->dumpFile($this->directory . '/review.php', '<?php /* changed code */');
        $this->expectExceptionObject(ApprovalFlowException::invalidArtifactManifest('Artifact "finance:review@1.0.0" does not match its retained bytes.'));

        $loader->load([EndExecutor::class]);
    }

    public function testMissingTransitiveDependencyIsNotSilentlySkipped(): void
    {
        $manifest = $this->manifest();
        array_shift($manifest['artifacts']);
        $this->writeManifest($manifest);
        $this->expectExceptionObject(ApprovalFlowException::invalidArtifactManifest('Artifact "core:runtime@1.0.0" is required but unavailable.'));

        $this->loader()->load([EndExecutor::class]);
    }

    public function testCyclicArtifactDependenciesAreRejected(): void
    {
        $manifest = $this->manifest();
        $manifest['artifacts'][0]['requires'] = ['finance:review@1.0.0'];
        $this->writeManifest($manifest);
        $this->expectExceptionObject(ApprovalFlowException::invalidArtifactManifest('Artifact dependency cycle at "finance:review@1.0.0".'));

        $this->loader()->load([EndExecutor::class]);
    }

    public function testDuplicateServiceBindingsFailInsteadOfSelectingAWinner(): void
    {
        $manifest = $this->manifest();
        $manifest['services'][] = ['class' => strtolower(EndExecutor::class), 'artifact' => 'core:runtime@1.0.0'];
        $this->writeManifest($manifest);
        $this->expectExceptionObject(ApprovalFlowException::invalidArtifactManifest('Service "' . strtolower(EndExecutor::class) . '" has more than one artifact binding.'));

        $this->loader()->load([EndExecutor::class]);
    }

    public function testDuplicateArtifactIdentitiesAreRejected(): void
    {
        $manifest = $this->manifest();
        $manifest['artifacts'][] = $manifest['artifacts'][0];
        $this->writeManifest($manifest);
        $this->expectExceptionObject(ApprovalFlowException::invalidArtifactManifest('Artifact "core:runtime@1.0.0" is declared more than once.'));

        $this->loader()->load([EndExecutor::class]);
    }

    public function testMalformedManifestIsRejected(): void
    {
        $this->filesystem->dumpFile($this->directory . '/manifest.json', '{"formatVersion":"1.0.0","artifacts":[],"services":[],"ignored":true}');
        try {
            $this->loader()->load([EndExecutor::class]);
            static::fail('Unknown deployment metadata must not be discarded.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_ARTIFACT_MANIFEST, $exception->getErrorCode());
        }
    }

    /**
     * @return array{formatVersion: string, artifacts: list<array{id: string, version: string, file: string, sha256: string, requires: list<string>}>, services: list<array{class: string, artifact: string}>}
     */
    private function manifest(): array
    {
        return [
            'formatVersion' => '1.0.0',
            'artifacts' => [
                ['id' => 'core:runtime', 'version' => '1.0.0', 'file' => 'runtime.php', 'sha256' => hash('sha256', $this->filesystem->readFile($this->directory . '/runtime.php')), 'requires' => []],
                ['id' => 'finance:review', 'version' => '1.0.0', 'file' => 'review.php', 'sha256' => hash('sha256', $this->filesystem->readFile($this->directory . '/review.php')), 'requires' => ['core:runtime@1.0.0']],
            ],
            'services' => [['class' => EndExecutor::class, 'artifact' => 'finance:review@1.0.0']],
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function writeManifest(array $manifest): void
    {
        $this->filesystem->dumpFile($this->directory . '/manifest.json', json_encode($manifest, \JSON_THROW_ON_ERROR));
    }

    private function loader(): ArtifactManifestLoader
    {
        $schema = $this->filesystem->readFile(\dirname(__DIR__, 7) . '/src/Core/Framework/ApprovalFlow/Resources/schema/publication/1.0.0/artifact-manifest.schema.json');

        return new ArtifactManifestLoader($this->filesystem, new GraphJsonDecoder(), new GraphSchemaValidator(), $schema, $this->directory . '/manifest.json');
    }
}
