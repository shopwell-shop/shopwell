<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\DataAbstractionLayer\StockUpdate;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal In order to manipulate the filter process, provide your own tagged AbstractStockUpdateFilter
 */
#[Package('framework')]
final readonly class StockUpdateFilterProvider
{
    /**
     * @internal
     *
     * @param AbstractStockUpdateFilter[] $filters
     */
    public function __construct(private iterable $filters)
    {
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    public function filterProductIdsForStockUpdates(array $ids, Context $context): array
    {
        foreach ($this->filters as $filter) {
            $ids = $filter->filter($ids, $context);
        }

        return $ids;
    }
}
