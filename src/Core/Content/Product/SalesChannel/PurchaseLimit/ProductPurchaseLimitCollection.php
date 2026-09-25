<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\PurchaseLimit;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * @codeCoverageIgnore
 *
 * @extends Collection<ProductPurchaseLimit>
 */
#[Package('inventory')]
class ProductPurchaseLimitCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return ProductPurchaseLimit::class;
    }
}
