<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Cms\ProductSlider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopwell\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Content\Product\Cms\ProductSlider\AbstractProductSliderProcessor;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AbstractProductSliderProcessor::class)]
class AbstractProductSliderProcessorTest extends TestCase
{
    use ProductSliderUnitTrait;

    public function testGetDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);
        (new TestAbstractProductSliderProcessor())->getDecorated();
    }

    public function testGetSource(): void
    {
        $processor = new TestAbstractProductSliderProcessor();
        static::assertSame('test', $processor->getSource());
    }

    public function testFilterOutOutOfStockHiddenCloseoutProducts(): void
    {
        $products = $this->getProducts();
        static::assertCount(2, $products);

        $processor = new TestAbstractProductSliderProcessor();

        $filteredProducts = $processor->publicFilterOutOutOfStockHiddenCloseoutProducts($products);
        static::assertCount(1, $filteredProducts);
    }

    public function testFilterOutOutOfStockHiddenCloseoutProductsForVariantProducts(): void
    {
        $product1Variant1 = (new ProductEntity())->assign(['id' => 'p1v1', 'stock' => 10]);
        $product1Variant2 = (new ProductEntity())->assign(['id' => 'p1v2', 'stock' => 0]);
        $product1 = (new ProductEntity())->assign([
            'id' => 'p1',
            'isCloseout' => true,
            'childCount' => 2,
            'children' => new ProductCollection([$product1Variant1, $product1Variant2]),
        ]);

        $product2 = (new ProductEntity())->assign([
            'id' => 'p2',
            'isCloseout' => true,
            'stock' => 0,
        ]);

        $product3 = (new ProductEntity())->assign([
            'id' => 'p3',
            'isCloseout' => false,
            'stock' => 0,
        ]);

        $products = new ProductCollection([$product1, $product2, $product3]);

        $processor = new TestAbstractProductSliderProcessor();

        $filteredProducts = $processor->publicFilterOutOutOfStockHiddenCloseoutProducts($products);
        static::assertCount(2, $filteredProducts);
        static::assertTrue($filteredProducts->has('p1'));
        static::assertTrue($filteredProducts->has('p3'));
    }
}

/**
 * @internal
 */
class TestAbstractProductSliderProcessor extends AbstractProductSliderProcessor
{
    public function getDecorated(): AbstractProductSliderProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function getSource(): string
    {
        return 'test';
    }

    public function collect(CmsSlotEntity $slot, FieldConfigCollection $config, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ElementDataCollection $result, ResolverContext $resolverContext): void
    {
        // nth
    }

    public function publicFilterOutOutOfStockHiddenCloseoutProducts(ProductCollection $products): ProductCollection
    {
        return $this->filterOutOutOfStockHiddenCloseoutProducts($products);
    }
}
