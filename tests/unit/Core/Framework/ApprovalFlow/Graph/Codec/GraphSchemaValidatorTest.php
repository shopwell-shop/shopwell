<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\Codec;

use Opis\JsonSchema\Exceptions\UnresolvedReferenceException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\ViolationCategory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphSchemaValidator;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(GraphSchemaValidator::class)]
class GraphSchemaValidatorTest extends TestCase
{
    public function testReturnsEveryFieldErrorWithJsonPointerEscaping(): void
    {
        $schema = '{"type":"object","properties":{"a/b~":{"type":"boolean"},"count":{"type":"integer"}},"additionalProperties":false}';
        $value = json_decode('{"a/b~":0,"count":false}', flags: \JSON_THROW_ON_ERROR);

        $violations = (new GraphSchemaValidator())->validate($value, $schema, '/nodes/1/config');

        static::assertCount(2, $violations);
        static::assertSame(['/nodes/1/config/a~1b~0', '/nodes/1/config/count'], array_column($violations, 'path'));
        static::assertSame(ViolationCategory::Structure, $violations[0]->category);
    }

    public function testDraftAndCompleteSchemasWithTheSameIdCannotShareCachedRules(): void
    {
        $validator = new GraphSchemaValidator();
        $draft = '{"$id":"urn:test:review","type":"object"}';
        $full = '{"$id":"urn:test:review","type":"object","required":["assignees"]}';

        static::assertSame([], $validator->validate(new \stdClass(), $draft));
        $violations = $validator->validate(new \stdClass(), $full, '/config', ViolationCategory::Draft);
        static::assertCount(1, $violations);
        static::assertSame(ViolationCategory::Draft, $violations[0]->category);
        static::assertSame('schema_required', $violations[0]->code);
    }

    public function testEmptyPropertyNameKeepsItsPointerSegment(): void
    {
        $value = json_decode('{"":0}', flags: \JSON_THROW_ON_ERROR);
        $violations = (new GraphSchemaValidator())->validate($value, '{"type":"object","properties":{"":{"type":"boolean"}}}', '/config');

        static::assertSame('/config/', $violations[0]->path);
    }

    public function testSchemaDefaultsNeverChangeTheInspectedValue(): void
    {
        $value = new \stdClass();
        $schema = '{"type":"object","properties":{"limit":{"type":"integer","default":10}}}';

        static::assertSame([], (new GraphSchemaValidator())->validate($value, $schema));
        static::assertSame([], get_object_vars($value));
    }

    public function testNeverResolvesRemoteSchemaReferences(): void
    {
        $this->expectException(UnresolvedReferenceException::class);

        (new GraphSchemaValidator())->validate(new \stdClass(), '{"$ref":"https://example.invalid/unavailable.schema.json"}');
    }

    public function testResolvesDefinitionsWithinTheSameArtifact(): void
    {
        $schema = '{"$id":"urn:test:local","type":"object","$defs":{"reviewer":{"type":"string"}},"properties":{"reviewer":{"$ref":"#/$defs/reviewer"}}}';
        $valid = json_decode('{"reviewer":"finance"}', flags: \JSON_THROW_ON_ERROR);
        $invalid = json_decode('{"reviewer":false}', flags: \JSON_THROW_ON_ERROR);
        $validator = new GraphSchemaValidator();

        static::assertSame([], $validator->validate($valid, $schema));
        static::assertSame('/reviewer', $validator->validate($invalid, $schema)[0]->path);
    }
}
