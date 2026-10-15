<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Cart;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\CartProcessorInterface;
use Shopwell\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopwell\Core\Checkout\Wallet\Service\WalletService;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

/**
 * Materialises the wallet payment requested through the `wallet-payment` cart extension into a server side
 * generated `credit` line item.
 *
 * The wallet amount is clamped to the customer balance, to the configured share of the goods subtotal and
 * to the amount that is still uncovered by other credit line items, so the checkout can never be overpaid.
 */
#[Package('checkout')]
class WalletCartProcessor implements CartProcessorInterface
{
    final public const EXTENSION_KEY = 'wallet-payment';

    final public const LINE_ITEM_ID = 'wallet-payment';

    final public const CONFIG_KEY_MAX_PAYMENT_PERCENT = 'core.wallet.maxPaymentPercent';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbsolutePriceCalculator $calculator,
        private readonly SystemConfigService $systemConfigService,
        private readonly WalletService $walletService
    ) {
    }

    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        $requested = $this->readRequestedAmount($toCalculate);

        $customer = $context->getCustomer();

        if ($requested <= 0.0 || $customer === null) {
            $toCalculate->getLineItems()->remove(self::LINE_ITEM_ID);

            return;
        }

        $currencyId = $context->getCurrency()->getId();
        $balance = $this->walletService->getBalance($customer->getId(), $currencyId, $context->getContext());
        $maxPercent = $this->systemConfigService->getFloat(self::CONFIG_KEY_MAX_PAYMENT_PERCENT);

        $available = $this->goodsTotal($toCalculate) - $this->otherCreditAmount($toCalculate);
        $amount = min($requested, $balance, max(0.0, $available) * $maxPercent / 100.0);

        if ($amount <= 0.0) {
            $toCalculate->getLineItems()->remove(self::LINE_ITEM_ID);

            return;
        }

        $this->materialize($toCalculate, $context, $amount);
    }

    private function materialize(Cart $cart, SalesChannelContext $context, float $amount): void
    {
        $lineItem = $cart->getLineItems()->get(self::LINE_ITEM_ID);

        // the line item may already be present as it is copied from the original cart by the credit processor;
        // only a genuinely new item is added, otherwise the collection would increase the quantity
        if (!$lineItem instanceof LineItem) {
            $lineItem = new LineItem(self::LINE_ITEM_ID, LineItem::CREDIT_LINE_ITEM_TYPE);
            $lineItem->setGood(false);
            $lineItem->setRemovable(false);
            $lineItem->setStackable(false);
            $lineItem->setLabel('wallet-payment');

            $cart->add($lineItem);
        }

        $lineItem->setPayloadValue('amount', $amount);
        $lineItem->setPriceDefinition(new AbsolutePriceDefinition(-$amount));
        $lineItem->setPrice($this->calculator->calculate(-$amount, $cart->getLineItems()->getPrices(), $context));
        $lineItem->setShippingCostAware(false);

        $cart->addExtension(self::EXTENSION_KEY, new ArrayStruct(['amount' => $amount]));
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

    private function readRequestedAmount(Cart $cart): float
    {
        $extension = $cart->getExtension(self::EXTENSION_KEY);

        if (!$extension instanceof ArrayStruct) {
            return 0.0;
        }

        $amount = $extension->get('amount');

        return is_numeric($amount) ? (float) $amount : 0.0;
    }
}
