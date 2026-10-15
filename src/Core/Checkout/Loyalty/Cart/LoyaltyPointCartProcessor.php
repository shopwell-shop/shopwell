<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Cart;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\CartProcessorInterface;
use Shopwell\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointService;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

/**
 * Materialises the points redemption requested through the `loyalty-points` cart extension into a
 * server side generated `credit` line item.
 *
 * The line item is never created by the Store API route itself but derived from the extension on every
 * recalculation. This keeps the cart consistent when other plugins recalculate it and lets the discount
 * be clamped to the current order total, the customer balance and the configured limits at any time.
 */
#[Package('checkout')]
class LoyaltyPointCartProcessor implements CartProcessorInterface
{
    final public const EXTENSION_KEY = 'loyalty-points';

    final public const LINE_ITEM_ID = 'loyalty-points';

    final public const CONFIG_KEY_POINT_VALUE = 'core.loyalty.pointValueInCurrency';

    final public const CONFIG_KEY_MIN_REDEEMABLE = 'core.loyalty.minRedeemablePoints';

    final public const CONFIG_KEY_MAX_REDEEMABLE_PERCENT = 'core.loyalty.maxRedeemablePercent';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbsolutePriceCalculator $calculator,
        private readonly SystemConfigService $systemConfigService,
        private readonly LoyaltyPointService $loyaltyPointService
    ) {
    }

    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        $points = $this->readRequestedPoints($toCalculate);

        if ($points <= 0) {
            $toCalculate->getLineItems()->remove(self::LINE_ITEM_ID);

            return;
        }

        $customer = $context->getCustomer();
        $pointValue = $this->systemConfigService->getFloat(self::CONFIG_KEY_POINT_VALUE);
        $minimum = $this->systemConfigService->getInt(self::CONFIG_KEY_MIN_REDEEMABLE);

        if ($customer === null || $pointValue <= 0.0) {
            $toCalculate->getLineItems()->remove(self::LINE_ITEM_ID);

            return;
        }

        $balance = $this->loyaltyPointService->getBalance($customer->getId(), $context->getContext());
        $points = min($points, $balance, $this->maxPointsByOrderTotal($toCalculate, $pointValue));

        if ($points < $minimum) {
            $toCalculate->getLineItems()->remove(self::LINE_ITEM_ID);

            return;
        }

        $amount = $points * $pointValue;

        $this->materialize($toCalculate, $context, $points, $amount);
    }

    private function materialize(Cart $cart, SalesChannelContext $context, int $points, float $amount): void
    {
        $lineItem = $cart->getLineItems()->get(self::LINE_ITEM_ID);

        // the line item may already be present as it is copied from the original cart by the credit processor;
        // only a genuinely new item is added, otherwise the collection would increase the quantity
        if (!$lineItem instanceof LineItem) {
            $lineItem = new LineItem(self::LINE_ITEM_ID, LineItem::CREDIT_LINE_ITEM_TYPE);
            $lineItem->setGood(false);
            $lineItem->setRemovable(false);
            $lineItem->setStackable(false);
            $lineItem->setLabel('loyalty-points');

            $cart->add($lineItem);
        }

        $lineItem->setPayloadValue('points', $points);
        $lineItem->setPriceDefinition(new AbsolutePriceDefinition(-$amount));
        $lineItem->setPrice($this->calculator->calculate(-$amount, $cart->getLineItems()->getPrices(), $context));
        $lineItem->setShippingCostAware(false);

        $cart->addExtension(self::EXTENSION_KEY, new ArrayStruct(['points' => $points]));
    }

    /**
     * The points must not cover more than the configured share of the goods subtotal that is not already
     * discounted by another credit line item.
     */
    private function maxPointsByOrderTotal(Cart $cart, float $pointValue): int
    {
        $maxPercent = $this->systemConfigService->getFloat(self::CONFIG_KEY_MAX_REDEEMABLE_PERCENT);
        $available = $this->goodsTotal($cart) - $this->otherCreditAmount($cart);

        return (int) floor(max(0.0, $available) * $maxPercent / 100.0 / $pointValue);
    }

    private function goodsTotal(Cart $cart): float
    {
        $total = 0.0;

        foreach ($cart->getLineItems()->filterGoods() as $lineItem) {
            $total += $lineItem->getPrice()?->getTotalPrice() ?? 0.0;
        }

        return $total;
    }

    /**
     * Sums up the credit line items that are not managed by this processor, so that the combined discount
     * of all credit line items can never exceed the goods subtotal.
     */
    private function otherCreditAmount(Cart $cart): float
    {
        $total = 0.0;

        foreach ($cart->getLineItems()->filterType(LineItem::CREDIT_LINE_ITEM_TYPE) as $lineItem) {
            if ($lineItem->getId() === self::LINE_ITEM_ID) {
                continue;
            }

            $total += abs($lineItem->getPrice()?->getTotalPrice() ?? 0.0);
        }

        return $total;
    }

    private function readRequestedPoints(Cart $cart): int
    {
        $extension = $cart->getExtension(self::EXTENSION_KEY);

        if (!$extension instanceof ArrayStruct) {
            return 0;
        }

        $points = $extension->get('points');

        return is_numeric($points) ? (int) $points : 0;
    }
}
