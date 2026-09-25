<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\SalesChannel;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to remove line items from cart
 */
#[Package('checkout')]
abstract class AbstractCartItemRemoveRoute
{
    abstract public function getDecorated(): AbstractCartItemRemoveRoute;

    abstract public function remove(Request $request, Cart $cart, SalesChannelContext $context): CartResponse;
}
