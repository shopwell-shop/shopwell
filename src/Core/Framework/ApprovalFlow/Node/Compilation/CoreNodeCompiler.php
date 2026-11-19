<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Node\Compilation;

use Opis\JsonSchema\Exceptions\SchemaException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphSchemaValidator;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\CoreNodeType;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalFieldConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalNodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\EndNodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\StartNodeConfiguration;
use Shopwell\Core\Framework\Log\Package;

/**
 * @phpstan-type ApprovalConfiguration object{
 *     assignees: non-empty-list<object{kind: 'core:users', userIds: non-empty-list<string>}>,
 *     policy: object{kind: 'core:single'|'core:all'|'core:any', reject: 'veto'},
 *     fields: list<object{field: string, access: 'hidden'|'readOnly'|'editable', required: bool, when?: object}>,
 *     emptyAssignees: 'incident'|'skip'
 * }&\stdClass
 *
 * @internal
 */
#[Package('framework')]
class CoreNodeCompiler implements NodeCompiler
{
    public const VERSION = '1.0.0';

    public function __construct(
        private readonly NodeTypeRegistry $nodeTypes,
        private readonly GraphJsonDecoder $jsonDecoder,
        private readonly GraphSchemaValidator $schemas,
    ) {
    }

    public function getTypes(): array
    {
        return [
            CoreNodeType::START => [self::VERSION],
            CoreNodeType::APPROVAL => [self::VERSION],
            CoreNodeType::END => [self::VERSION],
        ];
    }

    public function compile(string $type, string $version, string $configurationJson): NodeConfiguration
    {
        if (!\in_array($version, $this->getTypes()[$type] ?? [], true)) {
            throw ApprovalFlowException::nodeCompilerNotFound($type, $version);
        }
        $configuration = $this->decode($type, $version, $configurationJson);

        if ($type === CoreNodeType::START) {
            /** @var object{requestType: string}&\stdClass $configuration */
            return new StartNodeConfiguration($configuration->requestType);
        }
        if ($type === CoreNodeType::END) {
            /** @var object{outcome: 'approved'|'rejected'}&\stdClass $configuration */
            return new EndNodeConfiguration($configuration->outcome);
        }

        /** @var ApprovalConfiguration $configuration */
        return $this->compileApproval($configuration);
    }

    private function decode(string $type, string $version, string $json): \stdClass
    {
        try {
            $configuration = $this->jsonDecoder->decode($json);
        } catch (GraphJsonException $exception) {
            throw ApprovalFlowException::invalidNodeConfiguration($type, $version, $exception->violation->path, $exception->violation->message, $exception);
        }
        if (!$configuration instanceof \stdClass) {
            throw ApprovalFlowException::invalidNodeConfiguration($type, $version, '', 'Node configuration must be a JSON object.');
        }
        $specification = $this->nodeTypes->get($type, $version);
        try {
            $violations = $this->schemas->validate($configuration, $specification->configurationSchema);
        } catch (SchemaException $exception) {
            throw ApprovalFlowException::invalidNodeType($specification->source, 'configuration schema cannot be evaluated', $exception);
        }
        if ($violations !== []) {
            $violation = $violations[0];
            throw ApprovalFlowException::invalidNodeConfiguration($type, $version, $violation->path, $violation->message);
        }

        return $configuration;
    }

    /**
     * @param ApprovalConfiguration $configuration
     */
    private function compileApproval(object $configuration): ApprovalNodeConfiguration
    {
        $userIds = [];
        foreach ($configuration->assignees as $source) {
            foreach ($source->userIds as $userId) {
                $userIds[$userId] = $userId;
            }
        }
        if ($configuration->policy->kind === ApprovalNodeConfiguration::SINGLE && \count($userIds) !== 1) {
            throw $this->invalidApproval('/policy/kind', 'Single approval requires exactly one distinct user.');
        }

        $fields = [];
        $seenFields = [];
        foreach ($configuration->fields as $index => $field) {
            $path = '/fields/' . $index;
            if (isset($seenFields[$field->field])) {
                throw $this->invalidApproval($path . '/field', 'A field may have only one access rule.');
            }
            if (isset($field->when)) {
                throw $this->invalidApproval($path . '/when', 'Conditional field rules require expression compilation, which is not available.');
            }
            if ($field->required && $field->access !== ApprovalFieldConfiguration::EDITABLE) {
                throw $this->invalidApproval($path . '/required', 'A required field must be editable.');
            }
            $seenFields[$field->field] = true;
            $fields[] = new ApprovalFieldConfiguration($field->field, $field->access, $field->required);
        }

        return new ApprovalNodeConfiguration(array_values($userIds), $configuration->policy->kind, $fields, $configuration->emptyAssignees);
    }

    private function invalidApproval(string $path, string $reason): ApprovalFlowException
    {
        return ApprovalFlowException::invalidNodeConfiguration(CoreNodeType::APPROVAL, self::VERSION, $path, $reason);
    }
}
