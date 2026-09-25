<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Cms\ProductSlider;

use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopwell\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
abstract class AbstractProductSliderProcessor
{
    protected const PRODUCT_SLIDER_ENTITY_FALLBACK = 'product-slider-entity-fallback';

    abstract public function getDecorated(): AbstractProductSliderProcessor;

    abstract public function getSource(): string;

    abstract public function collect(CmsSlotEntity $slot, FieldConfigCollection $config, ResolverContext $resolverContext): ?CriteriaCollection;

    abstract public function enrich(CmsSlotEntity $slot, ElementDataCollection $result, ResolverContext $resolverContext): void;

    protected function filterOutOutOfStockHiddenCloseoutProducts(ProductCollection $products): ProductCollection
    {
        return $products->filter(static function (ProductEntity $product) {
            if ($product->getChildCount() > 0) {
                return true;
            }

            if ($product->getIsCloseout() && $product->getStock() <= 0) {
                return false;
            }

            return true;
        });
    }
}
