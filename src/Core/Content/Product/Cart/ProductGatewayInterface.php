<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Cart;

use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
interface ProductGatewayInterface
{
    /**
     * @param array<string> $ids
     */
    public function get(array $ids, SalesChannelContext $context): ProductCollection;
}
