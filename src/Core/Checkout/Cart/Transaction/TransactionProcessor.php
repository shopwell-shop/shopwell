<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Transaction;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopwell\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopwell\Core\Checkout\CheckoutPermissions;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class TransactionProcessor
{
    public function process(Cart $cart, SalesChannelContext $context): TransactionCollection
    {
        $price = $cart->getPrice()->getTotalPrice();
        $amount = new CalculatedPrice(
            $price,
            $price,
            $cart->getPrice()->getCalculatedTaxes(),
            $cart->getPrice()->getTaxRules()
        );

        if ($cart->getBehavior()?->hasPermission(CheckoutPermissions::KEEP_ORDER_TRANSACTION)) {
            $transaction = $cart->getTransactions()->first();
            if ($transaction === null) {
                return new TransactionCollection();
            }

            $transaction = clone $transaction;
            $transaction->setAmount($amount);

            return new TransactionCollection([$transaction]);
        }

        return new TransactionCollection([
            new Transaction($amount, $context->getPaymentMethod()->getId()),
        ]);
    }
}
