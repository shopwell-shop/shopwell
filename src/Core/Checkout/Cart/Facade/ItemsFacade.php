<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Facade;

use Shopwell\Core\Checkout\Cart\Facade\Traits\ItemsAddTrait;
use Shopwell\Core\Checkout\Cart\Facade\Traits\ItemsCountTrait;
use Shopwell\Core\Checkout\Cart\Facade\Traits\ItemsHasTrait;
use Shopwell\Core\Checkout\Cart\Facade\Traits\ItemsIteratorTrait;
use Shopwell\Core\Checkout\Cart\Facade\Traits\ItemsRemoveTrait;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * The ItemsFacade is a wrapper around a collection of line-items.
 *
 * @script-service cart_manipulation
 *
 * @implements \IteratorAggregate<array-key, ItemFacade|ContainerFacade>
 */
#[Package('checkout')]
class ItemsFacade implements \IteratorAggregate, \Countable
{
    use ItemsAddTrait;
    use ItemsCountTrait;
    use ItemsHasTrait;
    use ItemsIteratorTrait;
    use ItemsRemoveTrait;

    /**
     * @internal
     */
    public function __construct(
        private LineItemCollection $items,
        private ScriptPriceStubs $priceStubs,
        private CartFacadeHelper $helper,
        private SalesChannelContext $context
    ) {
    }

    private function getItems(): LineItemCollection
    {
        return $this->items;
    }
}
