<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Product\QuickView;

use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('discovery')]
class MinimalQuickViewPage extends Struct
{
    /**
     * @internal
     */
    public function __construct(
        protected ProductEntity $product,
    ) {
    }

    public function getProduct(): ProductEntity
    {
        return $this->product;
    }
}
