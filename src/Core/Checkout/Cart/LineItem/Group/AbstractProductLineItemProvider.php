<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\LineItem\Group;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
abstract class AbstractProductLineItemProvider
{
    abstract public function getDecorated(): AbstractProductLineItemProvider;

    abstract public function getProducts(Cart $cart): LineItemCollection;
}
