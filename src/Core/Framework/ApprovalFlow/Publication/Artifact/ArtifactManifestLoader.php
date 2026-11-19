<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Publication\Artifact;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphSchemaValidator;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Reads deployment-owned provenance, not client-authored configuration.
 * The deployment attests service origins and the complete dependency set; this adapter verifies retained bytes.
 *
 * @phpstan-type Artifact object{id: string, version: string, file: string, sha256: string, requires: list<string>}&\stdClass
 * @phpstan-type Manifest object{artifacts: list<Artifact>, services: list<object{class: string, artifact: string}>}&\stdClass
 *
 * @internal
 */
#[Package('framework')]
class ArtifactManifestLoader
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly GraphJsonDecoder $jsonDecoder,
        private readonly GraphSchemaValidator $schemas,
        private readonly string $schema,
        private readonly string $manifestPath,
    ) {
    }

    /**
     * @param non-empty-list<class-string> $serviceClasses
     */
    public function load(array $serviceClasses): string
    {
        try {
            $manifest = $this->jsonDecoder->decode($this->filesystem->readFile($this->manifestPath));
        } catch (IOExceptionInterface|HttpException $exception) {
            throw ApprovalFlowException::invalidArtifactManifest('The deployment manifest cannot be read as strict JSON.', $exception);
        }
        $violations = $this->schemas->validate($manifest, $this->schema);
        if ($violations !== []) {
            throw ApprovalFlowException::invalidArtifactManifest('Invalid deployment manifest at "' . $violations[0]->path . '": ' . $violations[0]->message);
        }
        /** @var Manifest $manifest */
        $artifacts = [];
        foreach ($manifest->artifacts as $artifact) {
            $identity = $artifact->id . '@' . $artifact->version;
            if (isset($artifacts[$identity])) {
                throw ApprovalFlowException::invalidArtifactManifest('Artifact "' . $identity . '" is declared more than once.');
            }
            if (\in_array('..', explode('/', $artifact->file), true) || \in_array('.', explode('/', $artifact->file), true)) {
                throw ApprovalFlowException::invalidArtifactManifest('Artifact files must stay within the deployment manifest directory.');
            }
            $artifacts[$identity] = $artifact;
        }
        $bindings = [];
        foreach ($manifest->services as $service) {
            $class = strtolower($service->class);
            if (isset($bindings[$class])) {
                throw ApprovalFlowException::invalidArtifactManifest('Service "' . $service->class . '" has more than one artifact binding.');
            }
            $bindings[$class] = $service->artifact;
        }

        $resolved = [];
        $active = [];
        $services = [];
        foreach (array_unique($serviceClasses) as $class) {
            $identity = $bindings[strtolower($class)] ?? throw ApprovalFlowException::artifactServiceNotFound($class);
            $this->resolve($identity, $artifacts, $resolved, $active);
            $services[$class] = ['class' => $class, 'artifact' => $identity];
        }
        ksort($resolved, \SORT_STRING);
        ksort($services, \SORT_STRING);
        $frozenArtifacts = [];
        foreach ($resolved as $identity => $artifact) {
            try {
                $bytes = $this->filesystem->readFile(\dirname($this->manifestPath) . '/' . $artifact->file);
            } catch (IOExceptionInterface $exception) {
                throw ApprovalFlowException::invalidArtifactManifest('Artifact "' . $identity . '" has no readable retained bytes.', $exception);
            }
            if (!hash_equals($artifact->sha256, Hasher::hash($bytes, 'sha256'))) {
                throw ApprovalFlowException::invalidArtifactManifest('Artifact "' . $identity . '" does not match its retained bytes.');
            }
            $requires = $artifact->requires;
            sort($requires, \SORT_STRING);
            $frozenArtifacts[] = ['id' => $artifact->id, 'version' => $artifact->version, 'sha256' => $artifact->sha256, 'requires' => $requires];
        }

        return json_encode([
            'formatVersion' => '1.0.0',
            'artifacts' => $frozenArtifacts,
            'services' => array_values($services),
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array<string, Artifact> $artifacts
     * @param array<string, Artifact> $resolved
     * @param array<string, true> $active
     */
    private function resolve(string $identity, array $artifacts, array &$resolved, array &$active): void
    {
        if (isset($resolved[$identity])) {
            return;
        }
        if (isset($active[$identity])) {
            throw ApprovalFlowException::invalidArtifactManifest('Artifact dependency cycle at "' . $identity . '".');
        }
        $artifact = $artifacts[$identity] ?? throw ApprovalFlowException::invalidArtifactManifest('Artifact "' . $identity . '" is required but unavailable.');
        $active[$identity] = true;
        foreach ($artifact->requires as $dependency) {
            $this->resolve($dependency, $artifacts, $resolved, $active);
        }
        unset($active[$identity]);
        $resolved[$identity] = $artifact;
    }
}
