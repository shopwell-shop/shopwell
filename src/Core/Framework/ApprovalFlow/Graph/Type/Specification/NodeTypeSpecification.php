<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Specification;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\NodeEffectType;
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

    public string $draftConfigurationSchema;

    /**
     * @var array<string, 'input'|'output'>
     */
    public array $ports;

    /**
     * @var non-empty-list<NodeEffectType>
     */
    public array $allowedEffects;

    /**
     * @param array<string, 'input'|'output'> $ports
     * @param array<NodeEffectType> $allowedEffects
     */
    public function __construct(
        public string $type,
        public string $version,
        public string $labelSnippet,
        public string $source,
        public string $configurationSchema,
        array $ports,
        array $allowedEffects,
        ?string $draftConfigurationSchema = null,
    ) {
        if (!preg_match('/^[a-z][a-z0-9_-]*:[a-z][a-zA-Z0-9_-]*$/D', $type)) {
            throw ApprovalFlowException::invalidNodeType($source, 'type must be a namespaced identifier');
        }
        if (!preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D', $version)) {
            throw ApprovalFlowException::invalidNodeType($source, 'version must be an exact major.minor.patch version');
        }
        $validatedPorts = [];
        foreach ($ports as $id => $direction) {
            if (!\is_string($id) || $id === '' || !\in_array($direction, ['input', 'output'], true)) {
                throw ApprovalFlowException::invalidNodeType($source, 'ports must map names to input or output');
            }
            $validatedPorts[$id] = $direction;
        }
        $this->ports = $validatedPorts;

        if ($allowedEffects === [] || !array_is_list($allowedEffects)) {
            throw ApprovalFlowException::invalidNodeType($source, 'allowedEffects must be a nonempty list of unique effect types');
        }
        $effects = [];
        foreach ($allowedEffects as $effect) {
            if (!$effect instanceof NodeEffectType || isset($effects[$effect->value])) {
                throw ApprovalFlowException::invalidNodeType($source, 'allowedEffects must be a nonempty list of unique effect types');
            }
            $effects[$effect->value] = $effect;
        }

        $this->allowedEffects = array_values($effects);
        $this->configurationSchemaId = self::parseSchemaId($configurationSchema, $source, 'configurationSchema');
        // Retain exact schema bytes: decoding into PHP arrays would merge JSON {} and [].
        $this->configurationSchemaHash = Hasher::hash($configurationSchema, 'sha256');
        $this->draftConfigurationSchema = $draftConfigurationSchema ?? $configurationSchema;
        if ($draftConfigurationSchema !== null) {
            self::parseSchemaId($draftConfigurationSchema, $source, 'draftConfigurationSchema');
        }
    }

    private static function parseSchemaId(string $json, string $source, string $field): string
    {
        try {
            $schema = json_decode($json, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ApprovalFlowException::invalidNodeType($source, $field . ' is not valid JSON', $exception);
        }
        $id = $schema instanceof \stdClass ? ($schema->{'$id'} ?? null) : null;
        if (!$schema instanceof \stdClass || ($schema->type ?? null) !== 'object'
            || !\is_string($id) || trim($id) === ''
        ) {
            throw ApprovalFlowException::invalidNodeType($source, $field . ' must declare an object and a nonempty $id');
        }

        return $id;
    }
}
