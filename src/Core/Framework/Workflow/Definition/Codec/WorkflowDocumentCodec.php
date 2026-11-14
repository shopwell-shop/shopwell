<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Definition\Codec;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Connection\WorkflowConnection;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;
use Shopwell\Core\Framework\Workflow\Definition\WorkflowDocument;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 *
 * @phpstan-type NodeData array{id: string, type: string, typeVersion: int, config: array<string, mixed>|\stdClass}
 * @phpstan-type ConnectionData array{id: string, source: string, sourcePort: string, target: string, targetPort: string}
 */
#[Package('framework')]
final readonly class WorkflowDocumentCodec
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    public function decode(string $json): WorkflowDocument
    {
        try {
            $data = json_decode($json, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw WorkflowException::invalidDocument('/', $exception->getMessage());
        }

        $values = $this->validateObject($data, new Assert\Collection([
            'schemaVersion' => [new Assert\NotNull(), new Assert\IdenticalTo(WorkflowDocument::SCHEMA_VERSION)],
            'policy' => new Assert\Sequentially([new Assert\NotBlank(), new Assert\Type('string'), new Assert\Regex('/^[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$/D'), new Assert\Length(max: 255)]),
            'nodes' => $this->objectList(1000),
            'connections' => $this->objectList(5000),
        ]), '');

        $nodes = [];
        foreach ($values['nodes'] as $index => $node) {
            $this->validateObject($node, new Assert\Collection([
                'id' => $this->identifier(),
                'type' => new Assert\Sequentially([new Assert\NotBlank(), new Assert\Type('string'), new Assert\Regex('/^[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$/D'), new Assert\Length(max: 255)]),
                'typeVersion' => new Assert\Sequentially([new Assert\NotNull(), new Assert\Type('integer'), new Assert\Positive()]),
                'config' => [new Assert\NotNull(), new Assert\AtLeastOneOf([new Assert\Type(\stdClass::class), new Assert\IdenticalTo([])])],
            ]), '/nodes/' . $index);

            // Empty maps follow the platform convention; nonempty numeric maps must retain their JSON shape.
            /** @var NodeData $nodeData */
            $nodeData = get_object_vars($node);
            $nodeData['config'] = $this->normalizeConfiguration($nodeData['config']);
            $nodes[] = new WorkflowNode($nodeData['id'], $nodeData['type'], $nodeData['typeVersion'], $nodeData['config']);
        }

        $connections = [];
        foreach ($values['connections'] as $index => $connection) {
            /** @var ConnectionData $connectionData */
            $connectionData = $this->validateObject($connection, new Assert\Collection([
                'id' => $this->identifier(),
                'source' => $this->identifier(),
                'sourcePort' => $this->port(),
                'target' => $this->identifier(),
                'targetPort' => $this->port(),
            ]), '/connections/' . $index);
            $connections[] = new WorkflowConnection(...$connectionData);
        }

        return new WorkflowDocument($nodes, $connections, $values['policy']);
    }

    public function encode(WorkflowDocument $document): string
    {
        return json_encode([
            'schemaVersion' => WorkflowDocument::SCHEMA_VERSION,
            'policy' => $document->policy,
            'nodes' => $document->nodes,
            'connections' => $document->connections,
        ], \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateObject(mixed $value, Assert\Collection $constraint, string $path): array
    {
        if (!$value instanceof \stdClass) {
            throw WorkflowException::invalidDocument($path ?: '/', 'Expected an object.');
        }

        $values = get_object_vars($value);
        $violations = $this->validator->validate($values, $constraint);
        if ($violations->count() > 0) {
            $violation = $violations->get(0);
            $fieldPath = str_replace(['[', ']'], ['/', ''], $violation->getPropertyPath());

            throw WorkflowException::invalidDocument($path . $fieldPath, (string) $violation->getMessage());
        }

        return $values;
    }

    /**
     * @param non-negative-int $max
     */
    private function objectList(int $max): Assert\Sequentially
    {
        return new Assert\Sequentially([new Assert\NotNull(), new Assert\Type('array'), new Assert\Count(max: $max), new Assert\All([new Assert\Type(\stdClass::class), new Assert\NotNull()])]);
    }

    private function identifier(): Assert\Sequentially
    {
        return new Assert\Sequentially([new Assert\NotBlank(), new Assert\Type('string'), new Assert\Regex('/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/D'), new Assert\Length(max: 128)]);
    }

    private function port(): Assert\Sequentially
    {
        return new Assert\Sequentially([new Assert\NotBlank(), new Assert\Type('string'), new Assert\Regex('/^[a-zA-Z][a-zA-Z0-9_-]*$/D'), new Assert\Length(max: 128)]);
    }

    private function normalizeConfiguration(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $values = array_map($this->normalizeConfiguration(...), get_object_vars($value));

            return $values !== [] && array_is_list($values) ? (object) $values : $values;
        }

        return \is_array($value) ? array_map($this->normalizeConfiguration(...), $value) : $value;
    }
}
