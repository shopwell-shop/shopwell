<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Order;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final readonly class RestoredOrder
{
    public function __construct(
        public OrderEntity $order,
        public SalesChannelContext $context,
        public Cart $cart,
    ) {
    }
}
