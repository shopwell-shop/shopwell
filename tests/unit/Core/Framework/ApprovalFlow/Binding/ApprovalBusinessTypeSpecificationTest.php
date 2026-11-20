<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeSpecification;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalBusinessTypeSpecification::class)]
class ApprovalBusinessTypeSpecificationTest extends TestCase
{
    public function testRetainsExactSchemaBytesAndTheirIdentity(): void
    {
        $schema = '{"$id":"urn:finance:expense:1","type":"object","properties":{},"required":[]}';

        $type = $this->specification(formSchema: $schema);

        static::assertSame('urn:finance:expense:1', $type->formSchemaId);
        static::assertSame($schema, $type->formSchema);
        static::assertSame(hash('sha256', $schema), $type->formSchemaHash);
    }

    public function testSchemaByteChangesProduceDifferentPinsEvenWithTheSameId(): void
    {
        $first = $this->specification(formSchema: '{"$id":"urn:expense","type":"object","properties":{}}');
        $second = $this->specification(formSchema: '{"$id":"urn:expense","type":"object","properties":{"amount":{"type":"integer"}}}');

        static::assertSame($first->formSchemaId, $second->formSchemaId);
        static::assertNotSame($first->formSchemaHash, $second->formSchemaHash);
    }

    #[DataProvider('invalidDeclarations')]
    public function testRejectsInvalidDeclarations(string $field, string $value, string $reason): void
    {
        $this->expectExceptionObject(ApprovalFlowException::invalidBusinessType('finance:expense.yaml', $reason));

        $this->specification(...[$field => $value]);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidDeclarations(): iterable
    {
        yield 'type requires namespace' => ['type', 'expense', 'type must be a namespaced identifier'];
        yield 'type cannot have a trailing newline' => ['type', "finance:expense\n", 'type must be a namespaced identifier'];
        yield 'version cannot float' => ['version', 'latest', 'version must be an exact major.minor.patch version'];
        yield 'list cannot be a form schema' => ['formSchema', '[]', 'formSchema must declare an object and a nonempty $id'];
        yield 'boolean schema cannot describe a form' => ['formSchema', 'true', 'formSchema must declare an object and a nonempty $id'];
        yield 'form requires object root' => ['formSchema', '{"$id":"urn:expense","type":"array"}', 'formSchema must declare an object and a nonempty $id'];
        yield 'form requires schema identity' => ['formSchema', '{"type":"object"}', 'formSchema must declare an object and a nonempty $id'];
        yield 'schema ID cannot be whitespace' => ['formSchema', '{"$id":" ","type":"object"}', 'formSchema must declare an object and a nonempty $id'];
    }

    public function testMalformedJsonRetainsTheParseFailure(): void
    {
        try {
            $this->specification(formSchema: '{');
            static::fail('Malformed form schemas must fail at declaration construction.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INVALID_BUSINESS_TYPE, $exception->getErrorCode());
            static::assertInstanceOf(\JsonException::class, $exception->getPrevious());
        }
    }

    public function testDisplayMetadataDoesNotControlBusinessTypeAdmission(): void
    {
        $type = $this->specification(labelSnippet: '', source: '');

        static::assertSame('', $type->labelSnippet);
        static::assertSame('', $type->source);
        static::assertSame('urn:expense', $type->formSchemaId);
    }

    private function specification(
        string $type = 'finance:expense',
        string $version = '1.0.0',
        string $labelSnippet = 'finance.approval.expense',
        string $formSchema = '{"$id":"urn:expense","type":"object"}',
        string $source = 'finance:expense.yaml',
    ): ApprovalBusinessTypeSpecification {
        return new ApprovalBusinessTypeSpecification(
            $type,
            $version,
            $labelSnippet,
            $source,
            $formSchema,
        );
    }
}
