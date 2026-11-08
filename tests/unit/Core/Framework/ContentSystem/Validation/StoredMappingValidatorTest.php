<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopwell\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopwell\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopwell\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopwell\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopwell\Core\Framework\ContentSystem\Validation\StoredMappingValidator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;

/**
 * The rules themselves are `Mapping/StoredMappingInspector`'s and are covered in its own test; what is left
 * here is the write path's rendering of them, which is the part the Administration's error handling reads.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoredMappingValidator::class)]
class StoredMappingValidatorTest extends TestCase
{
    private const CATEGORY_NAME_PATH = 'category.name';

    #[TestDox('reports nothing for an admissible mapping')]
    public function testAdmissibleMappingYieldsNoViolations(): void
    {
        $violations = $this->validator(mappable: true)->validate([$this->mappedElement()], 'category');

        static::assertCount(0, $violations);
    }

    /**
     * Keyed on the element and the mapped PROPERTY, matching `ViolationConstraintMapper`'s `/{id}/{key}`
     * shape, so the Administration highlights the control the author acted on. The code is the specific one
     * off the exception rather than a single mapping code: the write path is where a client needs to tell a
     * non-mappable property from an uncatalogued path, because it cannot re-derive that from the message.
     */
    #[TestDox('keys the violation on the mapped property and carries the specific error code')]
    public function testViolationShape(): void
    {
        $violations = $this->validator(mappable: false)->validate([$this->mappedElement()], 'category');

        static::assertCount(1, $violations);
        static::assertSame('/element-1/text', $violations->get(0)->getPropertyPath());
        static::assertSame(ContentSystemException::PROPERTY_NOT_MAPPABLE, $violations->get(0)->getCode());
        static::assertSame(
            ContentSystemException::propertyNotMappable('Sw:Content:Text', 'text')->getMessage(),
            $violations->get(0)->getMessage()
        );
    }

    /**
     * The mapped path travels as the violation's invalid value, so a client can name the offending choice
     * without parsing it back out of the message.
     */
    #[TestDox('carries the mapped path as the invalid value')]
    public function testViolationCarriesThePath(): void
    {
        $violations = $this->validator(mappable: false)->validate([$this->mappedElement()], 'category');

        static::assertSame(self::CATEGORY_NAME_PATH, $violations->get(0)->getInvalidValue());
    }

    public function testStoredCardinalityMismatchHasItsOwnWriteErrorCode(): void
    {
        $violations = $this->validator(mappable: true)->validate([$this->mappedElement(ContextType::Collection)], 'category');

        static::assertCount(1, $violations);
        static::assertSame('/element-1/text', $violations->get(0)->getPropertyPath());
        static::assertSame(ContentSystemException::MAPPING_CONTEXT_TYPE_MISMATCH, $violations->get(0)->getCode());
    }

    private function mappedElement(ContextType $contextType = ContextType::Single): StoredElement
    {
        return new StoredElement(
            id: 'element-1',
            component: 'Sw:Content:Text',
            contextDefinitions: new ContextDefinitions(consumers: [
                'text' => new ContextConsumer(
                    type: $contextType,
                    required: false,
                    scope: ConsumerScope::Root,
                    source: MappingSourceReference::fromRootPath(self::CATEGORY_NAME_PATH),
                ),
            ]),
        );
    }

    private function validator(bool $mappable): StoredMappingValidator
    {
        $typeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $typeRegistry->method('has')->willReturn(true);
        $typeRegistry->method('get')->willReturn(
            ContentSystemElementTypeSpecificationBuilder::create('Sw:Content:Text')
                ->primitive('text', 'string', mappable: $mappable)
                ->build()
        );

        $candidateRegistry = static::createStub(AbstractContentSystemMappingCandidateRegistry::class);
        $candidateRegistry->method('forRootSource')->willReturn([
            self::CATEGORY_NAME_PATH => new MappingCandidate(
                path: self::CATEGORY_NAME_PATH,
                label: 'a label',
                description: 'a description',
                group: 'basic',
                valueType: 'string',
            ),
        ]);

        return new StoredMappingValidator(new StoredMappingInspector(
            $typeRegistry,
            $candidateRegistry,
            new MappingTypeCompatibility(),
            new MappingConsumers(),
            new InlineMappingTokenParser(),
        ));
    }
}
