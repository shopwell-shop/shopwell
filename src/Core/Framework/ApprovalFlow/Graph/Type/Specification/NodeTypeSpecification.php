<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Specification;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;

/**
 * A declaration describes a node; it does not grant permission to publish or execute it.
 */
#[Package('framework')]
final readonly class NodeTypeSpecification
{
    public string $configurationSchemaId;

    public string $configurationSchemaHash;

    /**
     * @param array<string, 'input'|'output'> $ports
     */
    public function __construct(
        public string $type,
        public string $version,
        public string $labelSnippet,
        public string $source,
        public string $configurationSchema,
        public array $ports,
    ) {
        if (!preg_match('/^[a-z][a-z0-9_-]*:[a-z][a-zA-Z0-9_-]*$/D', $type)) {
            throw ApprovalFlowException::invalidNodeType($source, 'type must be a namespaced identifier');
        }
        if (!preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D', $version)) {
            throw ApprovalFlowException::invalidNodeType($source, 'version must be an exact major.minor.patch version');
        }
        if (trim($source) === '' || trim($labelSnippet) === '') {
            throw ApprovalFlowException::invalidNodeType($source, 'source and labelSnippet must be nonempty');
        }
        foreach ($ports as $id => $direction) {
            if (!\is_string($id) || $id === '' || !\in_array($direction, ['input', 'output'], true)) {
                throw ApprovalFlowException::invalidNodeType($source, 'ports must map names to input or output');
            }
        }

        try {
            $schema = json_decode($configurationSchema, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ApprovalFlowException::invalidNodeType($source, 'configurationSchema is not valid JSON', $exception);
        }
        $schemaId = $schema instanceof \stdClass ? ($schema->{'$id'} ?? null) : null;
        if (!$schema instanceof \stdClass
            || ($schema->type ?? null) !== 'object'
            || !\is_string($schemaId)
            || trim($schemaId) === ''
        ) {
            throw ApprovalFlowException::invalidNodeType($source, 'configurationSchema must declare an object and a nonempty $id');
        }

        // Retain exact schema bytes: decoding into PHP arrays would merge JSON {} and [].
        $this->configurationSchemaId = $schemaId;
        $this->configurationSchemaHash = Hasher::hash($configurationSchema, 'sha256');
    }
}
