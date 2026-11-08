<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopwell\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopwell\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopwell\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopwell\Core\Framework\ContentSystem\Layout\LayoutWriteBoundary;
use Shopwell\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopwell\Core\Framework\ContentSystem\Layout\StoredTreeStyleNormalizer;
use Shopwell\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopwell\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopwell\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopwell\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Shopwell\Core\Framework\ContentSystem\Mutation\MutationPipeline;
use Shopwell\Core\Framework\ContentSystem\Mutation\MutationResult;
use Shopwell\Core\Framework\ContentSystem\Mutation\Op\BindElement;
use Shopwell\Core\Framework\ContentSystem\Mutation\Op\DuplicateElement;
use Shopwell\Core\Framework\ContentSystem\Mutation\Op\InsertElement;
use Shopwell\Core\Framework\ContentSystem\Mutation\Op\MoveElement;
use Shopwell\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;
use Shopwell\Core\Framework\ContentSystem\Mutation\PropertyMappingMutationFactory;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
class RootSourceMutationSequenceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private MutationPipeline $pipeline;

    private PropertyMappingMutationFactory $mappingMutations;

    private AbstractContentSystemElementTypeRegistry $types;

    private AbstractContentSystemBindingSpecificationRegistry $bindings;

    private BindingApplicator $bindingApplicator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pipeline = static::getContainer()->get(MutationPipeline::class);
        $this->mappingMutations = static::getContainer()->get(PropertyMappingMutationFactory::class);
        $this->types = static::getContainer()->get(ContentSystemElementTypeRegistry::class);
        $this->bindings = static::getContainer()->get(ContentSystemBindingSpecificationRegistry::class);
        $this->bindingApplicator = static::getContainer()->get(BindingApplicator::class);
    }

    public function testRootSourceSurvivesStructuralBindingAndMappingEdits(): void
    {
        $tree = new StoredTree([new StoredElement('container', 'Sw:Grid:Container')], 'product');
        $inserted = $this->mutateTree(new InsertElement(
            $this->types,
            'Sw:Product:PriceDisplay',
            $this->bindings,
            $this->bindingApplicator,
            parentElementId: 'container',
            slot: 'content',
        ), $tree);
        $elementId = $inserted->affectedElementIds[0];
        $tree = $inserted->layout;
        static::assertSame('product', $tree->rootSource, 'after insert');
        static::assertSame('product', $tree->find($elementId)?->contextDefinitions->getAllConsumers()['product']->source?->displayName());

        $tree = static::getContainer()->get(StoredTreeStyleNormalizer::class)->normalize($tree);
        $tree = static::getContainer()->get(LayoutWriteBoundary::class)->apply($tree);
        static::assertSame('product', $tree->rootSource, 'after normalization/write boundary');

        $replaced = $this->mutateTree($this->replace($elementId), $tree);
        $tree = $replaced->layout;
        static::assertSame('product', $tree->rootSource, 'after replace');
        static::assertSame('product', $tree->find($elementId)?->contextDefinitions->getAllConsumers()['product']->source?->displayName());
        static::assertNotContains('product', $replaced->droppedWiring);

        $bound = $this->mutateTree(new BindElement($this->bindings, 'core:Sw:Product:Manufacturer', $elementId, $this->bindingApplicator), $tree);
        static::assertSame('product', $bound->layout->rootSource, 'after bind');
        static::assertSame('core:Sw:Product:Manufacturer', $bound->layout->find($elementId)?->attributedSpecifications['product']);
        $beforeMap = $this->mutateTree($this->mappingMutations->unmap($elementId, 'product'), $bound->layout)->layout;
        static::assertArrayNotHasKey('product', $beforeMap->find($elementId)?->contextDefinitions->getAllConsumers() ?? [], 'before map');
        $mapped = $this->mutateTree($this->mappingMutations->map($elementId, 'product', MappingSourceReference::root('product')), $beforeMap);
        static::assertSame('product', $mapped->layout->rootSource, 'after map');
        $mappedElement = $mapped->layout->find($elementId);
        static::assertNotNull($mappedElement, 'after map');
        $consumer = $mappedElement->contextDefinitions->getAllConsumers()['product'];
        static::assertSame('product', $consumer->source?->displayName(), 'after map');
        static::assertSame(ContextType::Single, $consumer->type, 'after map');
        static::assertSame(ConsumerScope::Root, $consumer->scope, 'after map');
        $duplicated = $this->mutateTree(new DuplicateElement($elementId), $mapped->layout);
        static::assertSame('product', $duplicated->layout->rootSource, 'after duplicate');
        $copyId = $duplicated->affectedElementIds[0];

        $unmapped = $this->mutateTree($this->mappingMutations->unmap($elementId, 'product'), $duplicated->layout);
        static::assertSame('product', $unmapped->layout->rootSource, 'after unmap');
        $moved = $this->mutateTree(new MoveElement($elementId), $unmapped->layout);
        static::assertSame('product', $moved->layout->rootSource, 'after move');
        $tree = static::getContainer()->get(LayoutWriteBoundary::class)->apply($moved->layout);

        static::assertSame('product', $tree->rootSource, 'after final write boundary');
        $original = $tree->find($elementId);
        static::assertNotNull($original, 'original after final write boundary');
        static::assertArrayNotHasKey('product', $original->contextDefinitions->getAllConsumers(), 'original remains unmapped after final write boundary');
        static::assertSame('product', $tree->find($copyId)?->contextDefinitions->getAllConsumers()['product']->source?->displayName());
    }

    public function testCategoryTreeDoesNotRetainOrSeedAProductOnlyMapping(): void
    {
        $element = StoredElementBuilder::create('Sw:Product:PriceDisplay', 'price')
            ->withConsumer('product', ContextType::Single, scope: ConsumerScope::Root, source: MappingSourceReference::root('product'))
            ->build();
        $replaced = $this->mutateTree($this->replace($element->id), new StoredTree([$element], 'category'));

        static::assertSame('category', $replaced->layout->rootSource);
        static::assertContains('product', $replaced->droppedWiring);
        static::assertArrayNotHasKey('product', $replaced->layout->roots[0]->contextDefinitions->getAllConsumers());

        $this->expectExceptionObject(ContentSystemException::unknownMappingPath('product', 'category'));

        $this->mutateTree($this->mappingMutations->map('price', 'product', MappingSourceReference::root('product')), $replaced->layout);
    }

    public function testUnboundDraftRemainsUnboundAfterScaffoldingAndNormalization(): void
    {
        $inserted = $this->mutateTree(new InsertElement($this->types, 'Sw:Product:PriceDisplay', $this->bindings, $this->bindingApplicator), new StoredTree([]));
        $tree = static::getContainer()->get(StoredTreeStyleNormalizer::class)->normalize($inserted->layout);
        $tree = static::getContainer()->get(LayoutWriteBoundary::class)->apply($tree);
        static::assertNull($tree->rootSource);
        static::assertSame([], $tree->roots[0]->contextDefinitions->getAllConsumers());
    }

    private function replace(string $elementId): ReplaceElement
    {
        return new ReplaceElement($this->types, $elementId, 'Sw:Product:Manufacturer', $this->bindings, $this->bindingApplicator, static::getContainer()->get(StoredMappingInspector::class));
    }

    private function mutateTree(LayoutMutation $mutation, StoredTree $tree): MutationResult
    {
        $rootContext = static::getContainer()->get(RootSourceRegistry::class)->resolveGated($tree->rootSource, Context::createDefaultContext());

        return $this->pipeline->run($mutation, $tree, $rootContext);
    }
}
