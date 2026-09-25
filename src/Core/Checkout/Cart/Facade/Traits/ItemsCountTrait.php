<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Facade\Traits;

use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
trait ItemsCountTrait
{
    private LineItemCollection $items;

    /**
     * `count()` returns the count of line-items in this collection.
     * Note that it does only count the line-items directly in this collection and not child line-items of those.
     *
     * @return int The number of line-items in this collection.
     */
    public function count(): int
    {
        return $this->getItems()->count();
    }
}
