<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopwell\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopwell\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopwell\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopwell\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopwell\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopwell\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopwell\Core\Framework\ContentSystem\Mutation\PropertyMappingMutationFactory;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopwell\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PropertyMappingMutationFactory::class)]
class PropertyMappingMutationFactoryTest extends TestCase
{
    public function testBuildsAMapMutationFromTheSuppliedSourceAndTarget(): void
    {
        $factory = $this->factory();
        $tree = new StoredTree([
            new StoredElement(
                'element-1',
                'Sw:Text',
                properties: ['text' => StoredValue::ofString('fallback')],
            ),
        ], 'product');

        $mapped = $factory->map(
            'element-1',
            'text',
            MappingSourceReference::fromRootPath('product.name'),
        );

        $tree = $mapped->apply($tree);
        static::assertSame('product.name', $tree->roots[0]->contextDefinitions->getAllConsumers()['text']->source?->displayName());
    }

    public function testBuildsAnUnmapMutationFromTheSuppliedTarget(): void
    {
        $element = StoredElementBuilder::create('Sw:Text', 'element-1')
            ->withProperty('text', 'fallback')
            ->withConsumer('text', ContextType::Single, scope: ConsumerScope::Root, source: MappingSourceReference::root('product', 'name'))->build();
        $tree = new StoredTree([$element], 'product');

        $result = $this->factory()->unmap('element-1', 'text')->apply($tree);

        static::assertArrayNotHasKey('text', $result->roots[0]->contextDefinitions->getAllConsumers());
        static::assertSame('fallback', $result->roots[0]->property('text')?->asString());
    }

    private function factory(): PropertyMappingMutationFactory
    {
        $specification = ContentSystemElementTypeSpecificationBuilder::create('Sw:Text')
            ->primitive('text', 'string', mappable: true)
            ->build();
        $typeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $typeRegistry->method('has')->willReturn(true);
        $typeRegistry->method('get')->willReturnCallback(
            static fn (string $name): ContentSystemElementTypeSpecification => $specification
        );

        $candidateRegistry = static::createStub(AbstractContentSystemMappingCandidateRegistry::class);
        $candidateRegistry->method('forRootSource')->willReturn([
            'product.name' => new MappingCandidate(
                'product.name',
                'product name',
                'The product name',
                'basic',
                'string',
                ContextType::Single,
            ),
        ]);

        return new PropertyMappingMutationFactory($typeRegistry, new StoredMappingInspector(
            $typeRegistry,
            $candidateRegistry,
            new MappingTypeCompatibility(),
            new MappingConsumers(),
            new InlineMappingTokenParser(),
        ));
    }
}
