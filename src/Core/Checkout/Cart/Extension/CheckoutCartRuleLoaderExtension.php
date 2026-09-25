<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Extension;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\Event\CartEvent;
use Shopwell\Core\Checkout\Cart\RuleLoaderResult;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 *
 * @extends Extension<RuleLoaderResult>
 */
#[Package('checkout')]
final class CheckoutCartRuleLoaderExtension extends Extension implements ShopwellSalesChannelEvent, CartEvent
{
    public const NAME = 'checkout.cart.rule-load';

    /**
     * @internal shopwell owns the __constructor, but the properties are public API
     */
    public function __construct(
        public readonly SalesChannelContext $salesChannelContext,
        public readonly Cart $originalCart,
        public readonly CartBehavior $cartBehavior,
        protected readonly bool $new,
    ) {
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getCart(): Cart
    {
        return $this->originalCart;
    }
}
