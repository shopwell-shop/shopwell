<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Facade\Traits;

use Shopwell\Core\Checkout\Cart\Facade\CartFacadeHelper;
use Shopwell\Core\Checkout\Cart\Facade\ContainerFacade;
use Shopwell\Core\Checkout\Cart\Facade\ItemFacade;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
trait ItemsIteratorTrait
{
    private CartFacadeHelper $helper;

    private LineItemCollection $items;

    private SalesChannelContext $context;

    /**
     * @internal should not be used directly, loop over an ItemsFacade directly inside twig instead
     *
     * @return \ArrayIterator<array-key, ItemFacade|ContainerFacade>
     */
    public function getIterator(): \ArrayIterator
    {
        $items = [];
        foreach ($this->getItems() as $key => $item) {
            $items[$key] = match ($item->getType()) {
                LineItem::CONTAINER_LINE_ITEM => new ContainerFacade($item, $this->priceStubs, $this->helper, $this->context),
                default => new ItemFacade($item, $this->priceStubs, $this->helper, $this->context),
            };
        }

        /**
         * We need to force the type here, as `ContainerFacade` extends `ItemFacade`, so `ItemFacade|ContainerFacade` is normalized to `ItemFacade`.
         * See https://github.com/phpstan/phpstan/discussions/12727
         *
         * @var \ArrayIterator<array-key, ItemFacade>
         */
        return new \ArrayIterator($items);
    }
}
