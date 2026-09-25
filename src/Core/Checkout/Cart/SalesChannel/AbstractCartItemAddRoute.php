<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\SalesChannel;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to add new line items to the cart
 */
#[Package('checkout')]
abstract class AbstractCartItemAddRoute
{
    abstract public function getDecorated(): AbstractCartItemAddRoute;

    /**
     * @param array<LineItem>|null $items
     */
    abstract public function add(Request $request, Cart $cart, SalesChannelContext $context, ?array $items): CartResponse;
}
