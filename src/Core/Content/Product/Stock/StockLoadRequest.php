<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Stock;

use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
class StockLoadRequest
{
    /**
     * @param array<string> $productIds
     */
    public function __construct(public array $productIds)
    {
    }
}
