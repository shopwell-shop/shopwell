<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\DataAbstractionLayer\StockUpdate;

use Shopwell\Core\Content\Product\DataAbstractionLayer\StockUpdate\AbstractStockUpdateFilter;
use Shopwell\Core\Framework\Context;

/**
 * @internal
 */
class TestStockUpdateFilter extends AbstractStockUpdateFilter
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private readonly array $ids)
    {
    }

    public function filter(array $ids, Context $context): array
    {
        return \array_values(\array_diff($ids, $this->ids));
    }
}
