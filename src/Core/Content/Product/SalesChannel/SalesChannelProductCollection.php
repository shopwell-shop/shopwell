<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel;

use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends ProductCollection<SalesChannelProductEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class SalesChannelProductCollection extends ProductCollection
{
    public function getApiAlias(): string
    {
        return 'sales_channel_product_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelProductEntity::class;
    }
}
