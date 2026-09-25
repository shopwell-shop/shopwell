<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Facade;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopwell\Core\Checkout\Cart\Processor;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class CartFacadeHelper
{
    /**
     * @internal
     */
    public function __construct(
        private readonly LineItemFactoryRegistry $factory,
        private readonly Processor $processor
    ) {
    }

    public function product(string $productId, int $quantity, SalesChannelContext $context): LineItem
    {
        $data = [
            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'id' => $productId,
            'referencedId' => $productId,
            'quantity' => $quantity,
        ];

        return $this->factory->create($data, $context);
    }

    public function calculate(Cart $cart, CartBehavior $behavior, SalesChannelContext $context): Cart
    {
        return $this->processor->process($cart, $context, $behavior);
    }
}
