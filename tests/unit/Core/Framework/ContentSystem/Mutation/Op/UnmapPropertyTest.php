<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopwell\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopwell\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopwell\Core\Framework\ContentSystem\Mutation\Op\UnmapProperty;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\ContentSystem\StubLoaderConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UnmapProperty::class)]
class UnmapPropertyTest extends TestCase
{
    public function testRemovesOnlyTheSelectedMappingAndKeepsItsAuthoredFallback(): void
    {
        $mapped = new ContextConsumer(
            ContextType::Single,
            required: false,
            scope: ConsumerScope::Root,
            source: MappingSourceReference::fromRootPath('product.name'),
        );
        $other = new ContextConsumer(ContextType::Single, required: false);
        $provider = new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple());
        $element = new StoredElement(
            'element-1',
            'Sw:Text',
            properties: [
                'text' => StoredValue::ofString('Text fallback'),
                'headline' => StoredValue::ofString('Headline fallback'),
            ],
            dataRequirements: ['selectedProduct' => new DataRequirement('selectedProduct', 'entity', new StubLoaderConfig())],
            contextDefinitions: new ContextDefinitions(providers: ['headline' => $provider], consumers: ['text' => $mapped, 'headline' => $other]),
        );
        $tree = new StoredTree([$element]);

        $mutation = new UnmapProperty('element-1', 'text');
        $unmapped = $mutation->apply($tree);

        $consumers = $unmapped->roots[0]->contextDefinitions->getAllConsumers();
        static::assertArrayNotHasKey('text', $consumers);
        static::assertSame($other, $consumers['headline']);
        static::assertSame(['headline' => $provider], $unmapped->roots[0]->contextDefinitions->getAllProviders());
        static::assertSame('Text fallback', $unmapped->roots[0]->property('text')?->asString());
        static::assertSame($element->dataRequirements, $unmapped->roots[0]->dataRequirements);
        static::assertSame(['element-1'], $mutation->affected());
        static::assertSame('product.name', $element->contextDefinitions->getAllConsumers()['text']->source?->displayName());
    }

    public function testLeavesTreeUntouchedWhenThePropertyIsNotMapped(): void
    {
        $consumer = new ContextConsumer(ContextType::Single, required: false);
        $element = new StoredElement(
            'element-1',
            'Sw:Text',
            contextDefinitions: new ContextDefinitions(consumers: ['text' => $consumer]),
        );
        $tree = new StoredTree([$element]);

        static::assertSame($tree, (new UnmapProperty('element-1', 'text'))->apply($tree));
    }

    public function testThrowsWhenTheTargetElementDoesNotExist(): void
    {
        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('missing'));

        (new UnmapProperty('missing', 'text'))->apply(new StoredTree([]));
    }
}
