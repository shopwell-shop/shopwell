<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\LineItem\Group;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('checkout')]
class ProductLineItemProvider extends AbstractProductLineItemProvider
{
    public function getDecorated(): AbstractProductLineItemProvider
    {
        throw new DecorationPatternException(self::class);
    }

    public function getProducts(Cart $cart): LineItemCollection
    {
        return $cart->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);
    }
}
