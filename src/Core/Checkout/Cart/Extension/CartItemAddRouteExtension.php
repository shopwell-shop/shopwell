<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Extension;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CartResponse>
 */
#[Package('checkout')]
final class CartItemAddRouteExtension extends Extension
{
    public const NAME = 'cart-item-add-route.add';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     *
     * @param array<LineItem>|null $items
     */
    public function __construct(
        public readonly Request $request,
        public readonly Cart $cart,
        public readonly SalesChannelContext $context,
        public readonly ?array $items,
    ) {
    }
}
