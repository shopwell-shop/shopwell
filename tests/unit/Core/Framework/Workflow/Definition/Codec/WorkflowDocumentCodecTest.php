<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Definition\Codec;

use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Codec\WorkflowDocumentCodec;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowDocumentCodec::class)]
class WorkflowDocumentCodecTest extends TestCase
{
    public function testUnknownNodeConfigurationSurvivesRoundTrip(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/../../_fixtures/automation.json');
        $codec = new WorkflowDocumentCodec(Validation::createValidator());

        $document = $codec->decode($json);

        static::assertSame('example:record', $document->nodes[1]->type);
        static::assertSame(2, $document->nodes[1]->typeVersion);
        static::assertIsArray($document->nodes[1]->config);
        static::assertSame(['value' => null, 'items' => [1, 'two', false]], $document->nodes[1]->config['payload']);
        static::assertJsonStringEqualsJsonString($json, $codec->encode($document));
    }

    public function testEmptyObjectConfigurationUsesDalCompatibleArray(): void
    {
        $codec = new WorkflowDocumentCodec(Validation::createValidator());
        $document = $codec->decode('{"schemaVersion":1,"policy":"core:structured","nodes":[{"id":"start","type":"core:start","typeVersion":1,"config":{}}],"connections":[]}');

        static::assertSame([], $document->nodes[0]->config);
        static::assertStringContainsString('"config":[]', $codec->encode($document));
    }

    public function testIncompleteDraftCanBeDecodedWithoutPublishingIt(): void
    {
        $codec = new WorkflowDocumentCodec(Validation::createValidator());

        $document = $codec->decode('{"schemaVersion":1,"policy":"core:structured","nodes":[],"connections":[]}');

        static::assertSame([], $document->nodes);
        static::assertSame([], $document->connections);
    }

    public function testNumericObjectKeysRetainTheirShapeAtEveryDepth(): void
    {
        $codec = new WorkflowDocumentCodec(Validation::createValidator());
        foreach (['{"0":"hello"}', '{"nested":{"0":"hello"},"list":[{"0":true}]}'] as $config) {
            $json = '{"schemaVersion":1,"policy":"core:structured","nodes":[{"id":"a","type":"example:node","typeVersion":1,"config":' . $config . '}],"connections":[]}';
            $encoded = $codec->encode($codec->decode($json));
            static::assertSame($json, $encoded);
            static::assertSame($encoded, $codec->encode($codec->decode($encoded)));
        }
    }

    #[DataProvider('invalidDocuments')]
    public function testRejectsMalformedDocuments(string $json): void
    {
        $codec = new WorkflowDocumentCodec(Validation::createValidator());

        $this->expectException(WorkflowException::class);
        $codec->decode($json);
    }

    public static function invalidDocuments(): \Generator
    {
        foreach (['id', 'type'] as $field) {
            $node = ['id' => 'a', 'type' => 'core:start', 'typeVersion' => 1, 'config' => []];
            $node[$field] = '';
            yield 'empty node ' . $field => [json_encode(['schemaVersion' => 1, 'policy' => 'core:structured', 'nodes' => [$node], 'connections' => []], \JSON_THROW_ON_ERROR)];
        }
        foreach (['id', 'source', 'sourcePort', 'target', 'targetPort'] as $field) {
            $connection = ['id' => 'edge', 'source' => 'a', 'sourcePort' => 'out', 'target' => 'b', 'targetPort' => 'in'];
            $connection[$field] = '';
            yield 'empty connection ' . $field => [json_encode(['schemaVersion' => 1, 'policy' => 'core:structured', 'nodes' => [], 'connections' => [$connection]], \JSON_THROW_ON_ERROR)];
        }
        yield 'invalid JSON' => ['{'];
        yield 'root must be a document' => ['[]'];
        yield 'future schema is not silently downgraded' => ['{"schemaVersion":2,"nodes":[],"connections":[]}'];
        yield 'version is not coerced' => ['{"schemaVersion":"1","nodes":[],"connections":[]}'];
        yield 'null schema version' => ['{"schemaVersion":null,"nodes":[],"connections":[]}'];
        yield 'object is not a node list' => ['{"schemaVersion":1,"policy":"core:structured","nodes":{},"connections":[]}'];
        yield 'unknown root field is not silently dropped' => ['{"schemaVersion":1,"policy":"core:structured","nodes":[],"connections":[],"viewport":{}}'];
        yield 'node map is not a node list' => ['{"schemaVersion":1,"policy":"core:structured","nodes":{"a":{}},"connections":[]}'];
        yield 'unqualified node type' => ['{"schemaVersion":1,"policy":"core:structured","nodes":[{"id":"a","type":"approval","typeVersion":1,"config":[]}],"connections":[]}'];
        yield 'configuration cannot be a nonempty list' => ['{"schemaVersion":1,"policy":"core:structured","nodes":[{"id":"a","type":"plugin:approval","typeVersion":1,"config":[1]}],"connections":[]}'];
        yield 'configuration cannot be null' => ['{"schemaVersion":1,"policy":"core:structured","nodes":[{"id":"a","type":"plugin:approval","typeVersion":1,"config":null}],"connections":[]}'];
        yield 'node type version must be positive' => ['{"schemaVersion":1,"policy":"core:structured","nodes":[{"id":"a","type":"plugin:approval","typeVersion":0,"config":[]}],"connections":[]}'];
        yield 'unknown node field is not silently dropped' => ['{"schemaVersion":1,"policy":"core:structured","nodes":[{"id":"a","type":"core:start","typeVersion":1,"config":[],"position":{"x":10}}],"connections":[]}'];
        yield 'connection requires ports' => ['{"schemaVersion":1,"policy":"core:structured","nodes":[],"connections":[{"id":"edge","source":"a","target":"b"}]}'];
    }

    #[DataProvider('invalidSchemaDocuments')]
    public function testDocumentSchemaAgreesWithDecoder(string $json, bool $valid): void
    {
        $schema = file_get_contents(\dirname(__DIR__, 7) . '/src/Core/Framework/Workflow/Definition/Schema/workflow-document.schema.json');
        static::assertNotFalse($schema);

        $result = (new Validator())->validate(json_decode($json, flags: \JSON_THROW_ON_ERROR), json_decode($schema, flags: \JSON_THROW_ON_ERROR));

        static::assertSame($valid, $result->isValid());

        $codec = new WorkflowDocumentCodec(Validation::createValidator());
        if (!$valid) {
            $this->expectException(WorkflowException::class);
        }
        $document = $codec->decode($json);
        static::assertSame(json_decode($json, true, flags: \JSON_THROW_ON_ERROR), json_decode($codec->encode($document), true, flags: \JSON_THROW_ON_ERROR));
    }

    public static function invalidSchemaDocuments(): \Generator
    {
        yield 'numeric configuration map' => ['{"schemaVersion":1,"policy":"core:structured","nodes":[{"id":"a","type":"core:start","typeVersion":1,"config":{"0":"hello"}}],"connections":[]}', true];
        yield 'empty draft' => ['{"schemaVersion":1,"policy":"core:structured","nodes":[],"connections":[]}', true];
        yield 'automation' => [(string) file_get_contents(__DIR__ . '/../../_fixtures/automation.json'), true];
        yield 'empty configuration object' => ['{"schemaVersion":1,"policy":"core:structured","nodes":[{"id":"a","type":"core:start","typeVersion":1,"config":{}}],"connections":[]}', true];

        foreach (self::invalidDocuments() as $name => [$json]) {
            if ($name !== 'invalid JSON') {
                yield $name => [$json, false];
            }
        }
    }
}
